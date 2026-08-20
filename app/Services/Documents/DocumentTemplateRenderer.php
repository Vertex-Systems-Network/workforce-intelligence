<?php

namespace App\Services\Documents;

use App\Models\DocumentComponent;
use App\Models\DocumentBrandKit;
use App\Models\DocumentPageMaster;
use App\Models\DocumentTemplate;
use App\Models\MediaAsset;
use App\Support\LocaleCatalog;
use Illuminate\Support\Facades\Storage;

/** Renders Document Studio V6 and legacy V4 schemas into paged HTML with a dependency-free legacy PDF fallback. */
class DocumentTemplateRenderer
{
    private array $componentCache = [];
    private ?int $brandLogoAssetId = null;

    /** Initializes nested logic, formulas and optional QR/barcode adapters. */
    public function __construct(
        private readonly DocumentExpressionEngine $expressions,
        private readonly DocumentCodeRenderer $codes,
    ) {}

    /** Builds HTML preview or a self-contained printable HTML document. */
    public function renderHtml(DocumentTemplate $template, array $context, bool $printable = false): string
    {
        $this->componentCache = [];
        $this->brandLogoAssetId = null;
        $settings = $this->settings($template);
        $pageMasterId=(int)($settings['page_master_id']??0);
        if($pageMasterId>0){$master=DocumentPageMaster::query()->where('workspace_id',$template->workspace_id)->find($pageMasterId);if($master){$settings['page']=array_merge($settings['page'],$master->page_settings??[]);$settings['header']=array_merge($settings['header'],$master->header_settings??[]);$settings['footer']=array_merge($settings['footer'],$master->footer_settings??[]);$settings['watermark']=array_merge($settings['watermark'],$master->watermark_settings??[]);}}
        $brandKitId=(int)($settings['brand_kit_id']??0);$brand=$brandKitId>0?DocumentBrandKit::query()->where('workspace_id',$template->workspace_id)->find($brandKitId):null;$this->brandLogoAssetId=$brand?->logo_media_asset_id ? (int)$brand->logo_media_asset_id : null;
        $direction = LocaleCatalog::direction($template->language);
        $primary = $this->safeColor($brand?->primary_color ?: $template->primary_color, '#111827');
        $secondary = $this->safeColor($brand?->secondary_color ?: $template->secondary_color, '#6B7280');
        $font = $this->safeFont($brand?->font_family ?: $template->font_family ?: 'Arial');
        $header = $this->repeatingRegion('header', $settings['header'], $context, $secondary);
        $footer = $this->repeatingRegion('footer', $settings['footer'], $context, $secondary);
        $watermark = $this->watermark($settings['watermark'], $context);
        $schema = is_array($template->content_schema) ? $template->content_schema : [];
        $authoredPages = array_values(array_filter($schema, fn ($block) => is_array($block) && ($block['type'] ?? null) === 'page'));
        $pages = '';
        if ($authoredPages) {
            foreach ($authoredPages as $index => $pageBlock) {
                $pageSettings=$this->pageSettings($settings,$pageBlock,$template->workspace_id);
                $pageHeader=$this->repeatingRegion('header',$pageSettings['header'],$context,$secondary);
                $pageFooter=$this->repeatingRegion('footer',$pageSettings['footer'],$context,$secondary);
                $pageWatermark=$this->watermark($pageSettings['watermark'],$context);
                $blocks = $this->renderBlocks(is_array($pageBlock['children'] ?? null) ? $pageBlock['children'] : [], $context, $template, $primary, $secondary, 1);
                $pageId = $this->e((string) ($pageBlock['id'] ?? 'page-'.($index + 1)));
                $pageStyle=$this->pageStyle($pageSettings['page']);
                $pages .= '<section class="wi-document-page" data-page-id="'.$pageId.'" dir="'.$direction.'" style="'.$pageStyle.'">'.$pageHeader.$pageWatermark.'<main class="wi-document-content">'.$blocks.'</main>'.$pageFooter.'</section>';
            }
        } else {
            $blocks = $this->renderBlocks($schema, $context, $template, $primary, $secondary, 0);
            $pages = '<section class="wi-document-page" data-page-id="legacy-page-1" dir="'.$direction.'">'.$header.$watermark.'<main class="wi-document-content">'.$blocks.'</main>'.$footer.'</section>';
        }
        $css = $this->documentCss($template, $settings, $font, $direction, $printable);
        if (! $printable) return '<div class="wi-document-preview">'.$css.$pages.'</div>';
        return '<!doctype html><html lang="'.$this->e($template->language).'" dir="'.$direction.'"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'.$css.'</head><body>'.$pages.'</body></html>';
    }

    /** Builds a dependency-free PDF for environments where Chromium rendering is unavailable. */
    public function renderPdf(DocumentTemplate $template, array $context): string
    {
        $schema = is_array($template->content_schema) ? $template->content_schema : [];
        $authoredPages = array_values(array_filter($schema, fn ($block) => is_array($block) && ($block['type'] ?? null) === 'page'));
        $pages = [[]];
        $lines = [];
        if ($authoredPages) {
            foreach ($authoredPages as $pageIndex => $pageBlock) {
                if ($pageIndex > 0) $lines[] = "\f";
                $lines = [...$lines, ...$this->flattenLines(is_array($pageBlock['children'] ?? null) ? $pageBlock['children'] : [], $context, $template, 1)];
            }
        } else $lines = $this->flattenLines($schema, $context, $template, 0);
        foreach ($lines as $line) {
            if ($line === "\f") {
                $pages[] = [];
                continue;
            }
            if (count($pages[array_key_last($pages)]) >= 47) $pages[] = [];
            $pages[array_key_last($pages)][] = $line;
        }
        if (count($pages) === 1 && $pages[0] === []) $pages[0][] = 'Document';
        return $this->pdfFromPages($pages, $template->orientation === 'landscape');
    }

    /** Replaces plain-text variable tokens with scalar context values. */
    public function resolve(string $value, array $context): string
    {
        return preg_replace_callback('/\{\{\s*([A-Za-z0-9_.-]+)\s*\}\}/', function (array $matches) use ($context) {
            $resolved = data_get($context, $matches[1], '');
            if (is_bool($resolved)) return $resolved ? 'Yes' : 'No';
            if (is_array($resolved) || is_object($resolved)) return '';
            return (string) ($resolved ?? '');
        }, $value) ?? $value;
    }

    /** Renders all blocks recursively while enforcing a hard nesting-depth limit. */
    private function renderBlocks(array $blocks, array $context, DocumentTemplate $template, string $primary, string $secondary, int $depth): string
    {
        if ($depth > 8) return '';
        $html = '';
        foreach ($blocks as $block) {
            if (! is_array($block)) continue;
            $html .= $this->htmlBlock($block, $context, $template, $primary, $secondary, $depth);
        }
        return $html;
    }

    /** Renders one V6-compatible block, including nested conditions, repeats, columns and reusable components. */
    private function htmlBlock(array $block, array $context, DocumentTemplate $template, string $primary, string $secondary, int $depth): string
    {
        $type = (string) ($block['type'] ?? 'text');
        $align = in_array(($block['align'] ?? ''), ['left', 'center', 'right', 'justify'], true) ? $block['align'] : 'start';
        $margin = 'margin:'.max(0, min(48, (int) ($block['margin_y'] ?? 8))).'px 0;';

        if ($type === 'page') return $this->renderBlocks(is_array($block['children'] ?? null) ? $block['children'] : [], $context, $template, $primary, $secondary, $depth + 1);
        if ($type === 'heading') {
            $level = min(3, max(1, (int) ($block['level'] ?? 2)));
            return '<h'.$level.' style="'.$margin.'text-align:'.$align.';color:'.$primary.'">'.$this->e($this->resolve((string) ($block['text'] ?? ''), $context)).'</h'.$level.'>';
        }
        if ($type === 'logo') {
            $logoAssetId=(int)($block['media_asset_id']??0);if($logoAssetId<1)$logoAssetId=(int)($this->brandLogoAssetId??0);
            if ($logoAssetId>0) {
                $image = $this->mediaImage($logoAssetId, $template, (string) ($block['alt'] ?? 'Logo'), min(100, max(10, (int) ($block['width'] ?? 34))), $align);
                if ($image !== '') return $image;
            }
            return '<div class="wi-doc-logo" style="'.$margin.'text-align:'.$align.';color:'.$primary.'">'.$this->e($this->resolve((string) ($block['label'] ?? '{{workspace.company_name}}'), $context)).'</div>';
        }
        if ($type === 'text') {
            return '<div class="wi-doc-text" style="'.$margin.'text-align:'.$align.'">'.$this->eWithBreaks($this->resolve((string) ($block['text'] ?? ''), $context)).'</div>';
        }
        if ($type === 'rich_text') {
            return '<div class="wi-doc-rich" style="'.$margin.'text-align:'.$align.'">'.$this->sanitizeRichHtml($this->resolveHtml((string) ($block['html'] ?? $block['text'] ?? ''), $context)).'</div>';
        }
        if ($type === 'field') {
            $value = $this->resolve((string) ($block['value'] ?? ''), $context);
            $prefix = $this->resolve((string) ($block['prefix'] ?? ''), $context);
            $suffix = $this->resolve((string) ($block['suffix'] ?? ''), $context);
            return '<div class="wi-doc-field" style="'.$margin.'text-align:'.$align.'">'.$this->e($prefix.$value.$suffix).'</div>';
        }
        if ($type === 'image') {
            $image = $this->mediaImage((int) ($block['media_asset_id'] ?? 0), $template, (string) ($block['alt'] ?? ''), min(100, max(10, (int) ($block['width'] ?? 100))), $align);
            $caption = trim($this->resolve((string) ($block['caption'] ?? ''), $context));
            return $image.($caption !== '' ? '<div class="wi-doc-caption" style="text-align:'.$align.'">'.$this->e($caption).'</div>' : '');
        }
        if ($type === 'divider') return '<hr class="wi-doc-divider" style="'.$margin.'border-color:'.$secondary.'">';
        if ($type === 'spacer') return '<div style="height:'.min(120, max(4, (int) ($block['height'] ?? 16))).'px"></div>';
        if ($type === 'page_break') return '<div class="wi-doc-page-break" aria-label="Page break"></div>';
        if ($type === 'signature') return $this->signatureBlock($block, $context, $secondary);
        if ($type === 'footer') return '<div class="wi-doc-flow-footer" style="'.$margin.'color:'.$secondary.'">'.$this->eWithBreaks($this->resolve((string) ($block['text'] ?? ''), $context)).'</div>';
        if ($type === 'key_value' || $type === 'totals') return $this->keyValueBlock($block, $context, $secondary, $type === 'totals');
        if ($type === 'table') return $this->tableBlock($block, $context, $secondary);
        if ($type === 'formula') {
            $value = $this->expressions->formula((string) ($block['expression'] ?? '0'), $context);
            $decimals = min(6, max(0, (int) ($block['decimals'] ?? 2)));
            $label = trim((string) ($block['label'] ?? ''));
            return '<div class="wi-doc-formula" style="'.$margin.'"><span>'.$this->e($label).'</span><strong>'.$this->e(number_format($value, $decimals, '.', ',')).'</strong></div>';
        }
        if ($type === 'conditional') {
            if (! $this->expressions->condition(is_array($block['condition'] ?? null) ? $block['condition'] : [], $context)) return '';
            return '<div class="wi-doc-conditional">'.$this->renderBlocks(is_array($block['children'] ?? null) ? $block['children'] : [], $context, $template, $primary, $secondary, $depth + 1).'</div>';
        }
        if ($type === 'repeat') {
            $source = data_get($context, (string) ($block['source'] ?? ''), []);
            $alias = preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', (string) ($block['alias'] ?? 'item')) ? (string) ($block['alias'] ?? 'item') : 'item';
            $children = is_array($block['children'] ?? null) ? $block['children'] : [];
            $html = '';
            $max = min(250, max(1, (int) ($block['max_items'] ?? 100)));
            foreach (is_iterable($source) ? $source : [] as $index => $row) {
                if ($index >= $max) break;
                $local = $context;
                $local[$alias] = is_array($row) ? $row : (array) $row;
                $local[$alias.'_index'] = $index + 1;
                $html .= '<div class="wi-doc-repeat-item">'.$this->renderBlocks($children, $local, $template, $primary, $secondary, $depth + 1).'</div>';
            }
            return $html;
        }
        if ($type === 'columns') {
            $columns = is_array($block['columns'] ?? null) ? array_slice($block['columns'], 0, 4) : [];
            $html = '<div class="wi-doc-columns" style="'.$margin.'">';
            foreach ($columns as $column) {
                if (! is_array($column)) continue;
                $width = min(100, max(10, (int) ($column['width'] ?? (100 / max(1, count($columns))))));
                $html .= '<div class="wi-doc-column" style="flex-basis:'.$width.'%">'.$this->renderBlocks(is_array($column['children'] ?? null) ? $column['children'] : [], $context, $template, $primary, $secondary, $depth + 1).'</div>';
            }
            return $html.'</div>';
        }
        if ($type === 'callout') {
            $tone = in_array(($block['tone'] ?? ''), ['neutral', 'info', 'success', 'warning', 'danger'], true) ? $block['tone'] : 'neutral';
            return '<div class="wi-doc-callout wi-doc-callout--'.$tone.'" style="'.$margin.'">'.$this->sanitizeRichHtml($this->resolveHtml((string) ($block['html'] ?? $block['text'] ?? ''), $context)).'</div>';
        }
        if ($type === 'stamp') {
            return '<div class="wi-doc-stamp" style="'.$margin.';color:'.$this->safeColor($block['color'] ?? null, $primary).'">'.$this->e($this->resolve((string) ($block['text'] ?? 'APPROVED'), $context)).'</div>';
        }
        if ($type === 'qr' || $type === 'barcode') {
            $value = $this->resolve((string) ($block['value'] ?? ''), $context);
            $svg = $type === 'qr' ? $this->codes->qr($value) : $this->codes->barcode($value);
            return '<div class="wi-doc-code wi-doc-code--'.$type.'" style="'.$margin.'">'.$svg.'</div>';
        }
        if ($type === 'reusable') {
            $componentId = (int) ($block['component_id'] ?? 0);
            if ($componentId <= 0) return '';
            $component = $this->componentCache[$componentId] ??= DocumentComponent::query()->where('workspace_id', $template->workspace_id)->find($componentId);
            if (! $component) return '';
            return '<div class="wi-doc-reusable">'.$this->renderBlocks($component->content_schema ?? [], $context, $template, $primary, $secondary, $depth + 1).'</div>';
        }
        if ($type === 'page_number') return '<div class="wi-doc-page-number">'.$this->e((string) ($block['label'] ?? 'Page')).'</div>';
        return '';
    }

    /** Renders a key/value or totals table with optional emphasized final values. */
    private function keyValueBlock(array $block, array $context, string $secondary, bool $totals): string
    {
        $rows = '';
        foreach (array_slice(is_array($block['items'] ?? null) ? $block['items'] : [], 0, 80) as $item) {
            if (! is_array($item)) continue;
            $rows .= '<tr><td style="color:'.$secondary.'">'.$this->e((string) ($item['label'] ?? '')).'</td><td'.($totals ? ' class="wi-doc-total-value"' : '').'>'.$this->e($this->resolve((string) ($item['value'] ?? ''), $context)).'</td></tr>';
        }
        return '<table class="wi-doc-key-values'.($totals ? ' wi-doc-totals' : '').'"><tbody>'.$rows.'</tbody></table>';
    }

    /** Renders a repeating data table with alignment, totals-safe values and optional header rows. */
    private function tableBlock(array $block, array $context, string $secondary): string
    {
        $source = data_get($context, (string) ($block['source'] ?? ''), []);
        $columns = array_slice(is_array($block['columns'] ?? null) ? $block['columns'] : [], 0, 20);
        $head = '';
        foreach ($columns as $column) {
            if (! is_array($column)) continue;
            $align = in_array(($column['align'] ?? ''), ['left', 'center', 'right'], true) ? $column['align'] : 'left';
            $width=isset($column['width'])?min(100,max(5,(int)$column['width'])):null;$widthCss=$width?'width:'.$width.'%;':'';$head .= '<th style="text-align:'.$align.';'.$widthCss.'color:'.$secondary.'">'.$this->e((string) ($column['label'] ?? $column['key'] ?? '')).'</th>';
        }
        $body = '';
        $max = min(1000, max(1, (int) ($block['max_rows'] ?? 250)));
        foreach (is_iterable($source) ? $source : [] as $index => $row) {
            if ($index >= $max) break;
            $row = is_array($row) ? $row : (array) $row;
            $body .= '<tr>';
            foreach ($columns as $column) {
                $key = (string) ($column['key'] ?? '');
                $align = in_array(($column['align'] ?? ''), ['left', 'center', 'right'], true) ? $column['align'] : 'left';
                $body .= '<td style="text-align:'.$align.'">'.$this->e($this->formatTableValue(data_get($row,$key,''),(string)($column['format']??'text'),$context)).'</td>';
            }
            $body .= '</tr>';
        }
        $header = ($block['show_header'] ?? true) ? '<thead><tr>'.$head.'</tr></thead>' : '';
        return '<table class="wi-doc-table">'.$header.'<tbody>'.$body.'</tbody></table>';
    }

    /** Formats one table cell with a bounded presentation mode without executing arbitrary code. */
    private function formatTableValue(mixed $value, string $format, array $context): string
    {
        if ($format === 'number' && is_numeric($value)) return number_format((float)$value, 2, '.', ',');
        if ($format === 'percent' && is_numeric($value)) return number_format((float)$value, 2, '.', ',').'%';
        if ($format === 'currency' && is_numeric($value)) {
            $currency=(string)(data_get($context,'currency')??data_get($context,'invoice.currency')??'');
            return trim(number_format((float)$value,2,'.',',').' '.$currency);
        }
        if ($format === 'date' && $value) {
            try { return \Carbon\CarbonImmutable::parse((string)$value)->toDateString(); } catch (\Throwable) { return (string)$value; }
        }
        if (is_bool($value)) return $value ? 'Yes' : 'No';
        if (is_array($value) || is_object($value)) return '';
        return (string)($value ?? '');
    }

    /** Renders a signature line or captured typed/drawn signature from the immutable render context. */
    private function signatureBlock(array $block, array $context, string $secondary): string
    {
        $role = trim((string) ($block['role'] ?? ''));
        $signatures = is_array($context['signatures'] ?? null) ? $context['signatures'] : [];
        $signature = collect($signatures)->first(function ($row) use ($role) {
            if (! is_array($row)) return false;
            return $role === '' || strcasecmp((string) ($row['role_label'] ?? ''), $role) === 0;
        });
        $label = $this->e($this->resolve((string) ($block['label'] ?? ($role !== '' ? $role : 'Signature')), $context));
        if (is_array($signature)) {
            $visual = '';
            $data = (string) ($signature['signature_data'] ?? '');
            if (str_starts_with($data, 'data:image/') && strlen($data) < 2_000_000) $visual = '<img class="wi-doc-signature-image" src="'.$this->e($data).'" alt="Signature">';
            if ($visual === '') $visual = '<div class="wi-doc-typed-signature">'.$this->e((string) ($signature['typed_name'] ?? $signature['signer_name'] ?? '')).'</div>';
            return '<div class="wi-doc-signature">'.$visual.'<div class="wi-doc-signature-line"></div><strong>'.$label.'</strong><small>'.$this->e((string) ($signature['signed_at'] ?? '')).'</small></div>';
        }
        return '<div class="wi-doc-signature"><div class="wi-doc-signature-space"></div><div class="wi-doc-signature-line"></div><strong>'.$label.'</strong></div>';
    }

    /** Embeds a workspace-owned image asset as a data URI so private media renders in preview and PDF. */
    private function mediaImage(int $assetId, DocumentTemplate $template, string $alt, int $width, string $align): string
    {
        if ($assetId <= 0) return '';
        $asset = MediaAsset::query()->where('workspace_id', $template->workspace_id)->where('status', 'active')->find($assetId);
        if (! $asset || $asset->category() !== 'image' || ! Storage::disk($asset->disk)->exists($asset->path)) return '';
        if ($asset->size_bytes > (int) config('documents.max_embedded_image_bytes', 5 * 1024 * 1024)) return '';
        $bytes = Storage::disk($asset->disk)->get($asset->path);
        return '<div class="wi-doc-image" style="text-align:'.$align.'"><img src="data:'.$this->e($asset->mime_type).';base64,'.base64_encode($bytes).'" alt="'.$this->e($alt ?: $asset->alt_text ?: $asset->name).'" style="max-width:'.$width.'%"></div>';
    }

    /** Builds a repeated fixed header or footer from template settings. */
    private function repeatingRegion(string $region, array $settings, array $context, string $secondary): string
    {
        if (! ($settings['enabled'] ?? false)) return '';
        $text = $this->resolve((string) ($settings['text'] ?? ''), $context);
        $divider = ($settings['divider'] ?? true) ? ' wi-document-'.$region.'--divider' : '';
        return '<div class="wi-document-'.$region.$divider.'" style="color:'.$secondary.'">'.$this->eWithBreaks($text).'</div>';
    }

    /** Renders an optional low-opacity repeated draft or confidentiality watermark. */
    private function watermark(array $settings, array $context): string
    {
        if (! ($settings['enabled'] ?? false)) return '';
        $text = trim($this->resolve((string) ($settings['text'] ?? 'DRAFT'), $context));
        if ($text === '') return '';
        $opacity = min(0.25, max(0.02, (float) ($settings['opacity'] ?? 0.08)));
        return '<div class="wi-document-watermark" style="opacity:'.$opacity.'">'.$this->e($text).'</div>';
    }

    /** Produces page-aware CSS for preview and headless-browser printing. */
    private function documentCss(DocumentTemplate $template, array $settings, string $font, string $direction, bool $printable): string
    {
        $page = $settings['page'];
        $paper = $template->paper_size === 'Letter' ? 'Letter' : 'A4';
        $orientation = $template->orientation === 'landscape' ? 'landscape' : 'portrait';
        $width = $paper === 'Letter' ? ($orientation === 'landscape' ? '279.4mm' : '215.9mm') : ($orientation === 'landscape' ? '297mm' : '210mm');
        $minHeight = $paper === 'Letter' ? ($orientation === 'landscape' ? '215.9mm' : '279.4mm') : ($orientation === 'landscape' ? '210mm' : '297mm');
        $marginTop = $this->mm($page['margin_top'] ?? 18);
        $marginRight = $this->mm($page['margin_right'] ?? 18);
        $marginBottom = $this->mm($page['margin_bottom'] ?? 20);
        $marginLeft = $this->mm($page['margin_left'] ?? 18);
        $background = $this->safeColor($page['background'] ?? null, '#FFFFFF');
        $bodyBackground = $printable ? '#fff' : '#eef1f5';
        return '<style>@page{size:'.$paper.' '.$orientation.';margin:0}*{box-sizing:border-box}html,body{margin:0;padding:0;background:'.$bodyBackground.'}body{font-family:'.$font.',Arial,sans-serif;color:#111827}.wi-document-preview{padding:18px;overflow:auto;background:#eef1f5}.wi-document-page{position:relative;direction:'.$direction.';width:'.$width.';min-height:'.$minHeight.';margin:0 auto 18px;background:var(--wi-doc-bg,'.$background.');box-shadow:'.($printable ? 'none' : '0 8px 30px rgba(15,23,42,.12)').';padding:var(--wi-doc-mt,'.$marginTop.') var(--wi-doc-mr,'.$marginRight.') var(--wi-doc-mb,'.$marginBottom.') var(--wi-doc-ml,'.$marginLeft.');overflow:hidden}.wi-document-content{position:relative;z-index:2}.wi-document-header,.wi-document-footer{position:'.($printable ? 'fixed' : 'absolute').';left:var(--wi-doc-ml,'.$marginLeft.');right:var(--wi-doc-mr,'.$marginRight.');z-index:3;font-size:10px;line-height:1.4}.wi-document-header{top:6mm;padding-bottom:3mm}.wi-document-footer{bottom:6mm;padding-top:3mm}.wi-document-header--divider{border-bottom:1px solid #e5e7eb}.wi-document-footer--divider{border-top:1px solid #e5e7eb}.wi-document-watermark{position:fixed;z-index:1;top:42%;left:8%;right:8%;font-size:64px;font-weight:800;text-align:center;transform:rotate(-28deg);color:#64748b;letter-spacing:8px;pointer-events:none}.wi-doc-logo{font-size:19px;font-weight:800}.wi-doc-text,.wi-doc-rich,.wi-doc-field{font-size:12px;line-height:1.6}.wi-doc-rich p{margin:5px 0}.wi-doc-rich h1,.wi-doc-rich h2,.wi-doc-rich h3{margin:10px 0 5px}.wi-doc-rich blockquote{border-inline-start:3px solid #cbd5e1;margin:8px 0;padding-inline-start:10px;color:#475569}.wi-doc-divider{border:0;border-top:1px solid;margin:12px 0;opacity:.5}.wi-doc-key-values,.wi-doc-table{border-collapse:collapse;width:100%;margin:10px 0;font-size:11px}.wi-doc-key-values td{padding:5px 7px;border-bottom:1px solid #f1f5f9}.wi-doc-key-values td:last-child{text-align:end;font-weight:600}.wi-doc-totals{max-width:360px;margin-inline-start:auto}.wi-doc-total-value{font-weight:800!important}.wi-doc-table th,.wi-doc-table td{padding:6px 7px;border-bottom:1px solid #e5e7eb;vertical-align:top}.wi-doc-table th{font-weight:700;background:#f8fafc}.wi-doc-image img{height:auto;max-height:190mm;object-fit:contain}.wi-doc-caption{font-size:9px;color:#64748b;margin-top:3px}.wi-doc-signature{width:235px;margin-top:26px;font-size:10px}.wi-doc-signature-space{height:42px}.wi-doc-signature-line{border-top:1px solid #94a3b8;margin-top:4px;padding-top:4px}.wi-doc-signature strong,.wi-doc-signature small{display:block;margin-top:3px}.wi-doc-signature-image{max-width:190px;max-height:52px}.wi-doc-typed-signature{font-size:23px;font-family:cursive;min-height:38px}.wi-doc-formula{display:flex;justify-content:space-between;gap:16px;padding:8px 10px;background:#f8fafc;border-radius:5px}.wi-doc-columns{display:flex;gap:10px;align-items:flex-start}.wi-doc-column{min-width:0;flex-grow:1}.wi-doc-callout{padding:10px 12px;border:1px solid #cbd5e1;border-inline-start-width:4px;border-radius:6px;font-size:11px}.wi-doc-callout--info{background:#eff6ff;border-color:#60a5fa}.wi-doc-callout--success{background:#f0fdf4;border-color:#4ade80}.wi-doc-callout--warning{background:#fffbeb;border-color:#fbbf24}.wi-doc-callout--danger{background:#fef2f2;border-color:#f87171}.wi-doc-stamp{display:inline-block;border:3px double currentColor;border-radius:6px;padding:5px 12px;font-size:18px;font-weight:800;letter-spacing:2px;transform:rotate(-4deg)}.wi-doc-code svg{max-width:180px;max-height:180px}.document-code-fallback{display:inline-grid;gap:3px;border:1px dashed #94a3b8;padding:8px;font-size:9px}.document-code-fallback small{color:#b45309}.wi-doc-flow-footer{border-top:1px solid #e5e7eb;padding-top:7px;font-size:9px}.wi-doc-page-break{break-after:page;page-break-after:always;height:0}.wi-document-preview .wi-doc-page-break{height:24px;margin:14px -18mm;border-top:2px dashed #cbd5e1;break-after:auto}.wi-doc-repeat-item{break-inside:avoid}.wi-doc-page-number{text-align:center;color:#64748b;font-size:9px}.wi-doc-reusable{display:contents}@media print{body{background:#fff}.wi-document-page{margin:0;box-shadow:none;break-after:page}.wi-document-preview{padding:0;background:#fff}}</style>';
    }

    /** Resolves one page's linked master and local overrides without mutating template-level settings. */
    private function pageSettings(array $base, array $pageBlock, int $workspaceId): array
    {
        $settings=$base;
        $overrideMasterId=(int)($pageBlock['page_master_id']??0);
        if($overrideMasterId>0){$master=DocumentPageMaster::query()->where('workspace_id',$workspaceId)->find($overrideMasterId);if($master){$settings['page']=array_merge($settings['page'],$master->page_settings??[]);$settings['header']=array_merge($settings['header'],$master->header_settings??[]);$settings['footer']=array_merge($settings['footer'],$master->footer_settings??[]);$settings['watermark']=array_merge($settings['watermark'],$master->watermark_settings??[]);}}
        foreach(['page','header','footer','watermark'] as $section){$field=$section.'_settings';if(is_array($pageBlock[$field]??null))$settings[$section]=array_merge($settings[$section],$pageBlock[$field]);}
        return $settings;
    }

    /** Builds bounded CSS custom properties for per-page margins and background. */
    private function pageStyle(array $page): string
    {
        return '--wi-doc-mt:'.$this->mm($page['margin_top']??18).';--wi-doc-mr:'.$this->mm($page['margin_right']??18).';--wi-doc-mb:'.$this->mm($page['margin_bottom']??20).';--wi-doc-ml:'.$this->mm($page['margin_left']??18).';--wi-doc-bg:'.$this->safeColor($page['background']??null,'#FFFFFF').';';
    }

    /** Normalizes template V6 page/header/footer/watermark settings with conservative defaults. */
    private function settings(DocumentTemplate $template): array
    {
        $settings = is_array($template->settings) ? $template->settings : [];
        return [
            'page' => array_merge(['margin_top' => 18, 'margin_right' => 18, 'margin_bottom' => 20, 'margin_left' => 18, 'background' => '#FFFFFF'], is_array($settings['page'] ?? null) ? $settings['page'] : []),
            'header' => array_merge(['enabled' => false, 'text' => '', 'divider' => true], is_array($settings['header'] ?? null) ? $settings['header'] : []),
            'footer' => array_merge(['enabled' => false, 'text' => '', 'divider' => true], is_array($settings['footer'] ?? null) ? $settings['footer'] : []),
            'watermark' => array_merge(['enabled' => false, 'text' => 'DRAFT', 'opacity' => 0.08], is_array($settings['watermark'] ?? null) ? $settings['watermark'] : []),
            'brand_kit_id' => isset($settings['brand_kit_id']) ? (int)$settings['brand_kit_id'] : null,
            'page_master_id' => isset($settings['page_master_id']) ? (int)$settings['page_master_id'] : null,
        ];
    }

    /** Resolves variables inside authored rich HTML while escaping context values before insertion. */
    private function resolveHtml(string $html, array $context): string
    {
        return preg_replace_callback('/\{\{\s*([A-Za-z0-9_.-]+)\s*\}\}/', function (array $matches) use ($context) {
            $value = data_get($context, $matches[1], '');
            if (is_array($value) || is_object($value)) return '';
            return $this->e((string) ($value ?? ''));
        }, $html) ?? $html;
    }

    /** Sanitizes editor-authored HTML using a strict tag/attribute allowlist without requiring ext-dom. */
    private function sanitizeRichHtml(string $html): string
    {
        $html = strip_tags($html, '<p><br><strong><b><em><i><u><s><ul><ol><li><blockquote><h1><h2><h3><a><code><pre>');
        $html = preg_replace('/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html) ?? $html;
        $html = preg_replace('/\s+style\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html) ?? $html;
        $html = preg_replace_callback('/<a\b([^>]*)>/i', function (array $matches) {
            preg_match('/href\s*=\s*["\']([^"\']+)["\']/i', $matches[1], $hrefMatch);
            $href = trim((string) ($hrefMatch[1] ?? ''));
            if (! preg_match('#^(https?://|mailto:)#i', $href)) return '<a>';
            return '<a href="'.$this->e($href).'" rel="noopener noreferrer">';
        }, $html) ?? $html;
        return $html;
    }

    /** Flattens nested V4 blocks into plain lines for the legacy PDF fallback. */
    private function flattenLines(array $blocks, array $context, DocumentTemplate $template, int $depth): array
    {
        if ($depth > 8) return [];
        $lines = [];
        foreach ($blocks as $block) {
            if (! is_array($block)) continue;
            $type = (string) ($block['type'] ?? 'text');
            if ($type === 'page_break') {
                $lines[] = "\f";
                continue;
            }
            if ($type === 'conditional') {
                if ($this->expressions->condition(is_array($block['condition'] ?? null) ? $block['condition'] : [], $context)) $lines = [...$lines, ...$this->flattenLines(is_array($block['children'] ?? null) ? $block['children'] : [], $context, $template, $depth + 1)];
                continue;
            }
            if ($type === 'repeat') {
                $source = data_get($context, (string) ($block['source'] ?? ''), []);
                $alias = (string) ($block['alias'] ?? 'item');
                foreach (is_iterable($source) ? $source : [] as $row) {
                    $local = $context;
                    $local[$alias] = is_array($row) ? $row : (array) $row;
                    $lines = [...$lines, ...$this->flattenLines(is_array($block['children'] ?? null) ? $block['children'] : [], $local, $template, $depth + 1)];
                }
                continue;
            }
            if ($type === 'columns') {
                foreach (is_array($block['columns'] ?? null) ? $block['columns'] : [] as $column) if (is_array($column)) $lines = [...$lines, ...$this->flattenLines(is_array($column['children'] ?? null) ? $column['children'] : [], $context, $template, $depth + 1)];
                continue;
            }
            if ($type === 'reusable') {
                $component = DocumentComponent::query()->where('workspace_id', $template->workspace_id)->find((int) ($block['component_id'] ?? 0));
                if ($component) $lines = [...$lines, ...$this->flattenLines($component->content_schema ?? [], $context, $template, $depth + 1)];
                continue;
            }
            $lines = [...$lines, ...$this->blockLines($block, $context)];
        }
        return $lines;
    }

    /** Converts one non-nested block to text lines for the legacy PDF fallback. */
    private function blockLines(array $block, array $context): array
    {
        $type = (string) ($block['type'] ?? 'text');
        if ($type === 'divider') return [str_repeat('-', 92)];
        if ($type === 'spacer') return [''];
        if ($type === 'logo') return [$this->resolve((string) ($block['label'] ?? '{{workspace.company_name}}'), $context), ''];
        if ($type === 'heading') return [$this->resolve((string) ($block['text'] ?? ''), $context), ''];
        if (in_array($type, ['text', 'field', 'footer'], true)) return preg_split('/\r\n|\r|\n/', $this->resolve((string) ($block['text'] ?? $block['value'] ?? ''), $context)) ?: [];
        if ($type === 'rich_text' || $type === 'callout') return [trim(strip_tags($this->resolve((string) ($block['html'] ?? $block['text'] ?? ''), $context)))];
        if ($type === 'image') return ['[Image: '.($block['alt'] ?? 'media').']'];
        if ($type === 'signature') return ['', '', '____________________________', $this->resolve((string) ($block['label'] ?? 'Signature'), $context)];
        if ($type === 'formula') return [(string) ($block['label'] ?? 'Formula').': '.number_format($this->expressions->formula((string) ($block['expression'] ?? '0'), $context), min(6, max(0, (int) ($block['decimals'] ?? 2))), '.', ',')];
        if ($type === 'qr' || $type === 'barcode') return ['['.strtoupper($type).': '.$this->resolve((string) ($block['value'] ?? ''), $context).']'];
        if ($type === 'stamp') return ['['.$this->resolve((string) ($block['text'] ?? 'APPROVED'), $context).']'];
        if ($type === 'key_value' || $type === 'totals') {
            $lines = [];
            foreach (is_array($block['items'] ?? null) ? $block['items'] : [] as $item) if (is_array($item)) $lines[] = $this->truncate((string) ($item['label'] ?? ''), 36).' : '.$this->resolve((string) ($item['value'] ?? ''), $context);
            return $lines;
        }
        if ($type === 'table') {
            $columns = is_array($block['columns'] ?? null) ? $block['columns'] : [];
            $source = data_get($context, (string) ($block['source'] ?? ''), []);
            $lines = [implode(' | ', array_map(fn ($column) => $this->truncate((string) ($column['label'] ?? $column['key'] ?? ''), 18), $columns)), str_repeat('-', 92)];
            foreach (is_iterable($source) ? $source : [] as $row) {
                $row = is_array($row) ? $row : (array) $row;
                $lines[] = implode(' | ', array_map(fn ($column) => $this->truncate((string) data_get($row, (string) ($column['key'] ?? ''), ''), 18), $columns));
            }
            return $lines;
        }
        return [];
    }

    /** Builds the compact legacy PDF object graph used only as an environment-safe fallback. */
    private function pdfFromPages(array $pages, bool $landscape): string
    {
        $objects = [];
        $pageIds = [];
        $fontId = 3;
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $width = $landscape ? 842 : 595;
        $height = $landscape ? 595 : 842;
        $fontSize = $landscape ? 8 : 9;
        $startY = $height - 36;
        foreach ($pages as $index => $pageLines) {
            $pageId = 4 + $index * 2;
            $contentId = $pageId + 1;
            $pageIds[] = $pageId;
            $stream = "BT\n/F1 {$fontSize} Tf\n36 {$startY} Td\n";
            foreach ($pageLines as $lineIndex => $line) {
                if ($lineIndex > 0) $stream .= "0 -15 Td\n";
                $stream .= '('.$this->pdfEscape((string) $line).") Tj\n";
            }
            $stream .= 'ET';
            $objects[$pageId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 '.$width.' '.$height.'] /Resources << /Font << /F1 '.$fontId.' 0 R >> >> /Contents '.$contentId.' 0 R >>';
            $objects[$contentId] = '<< /Length '.strlen($stream).' >>' . "\nstream\n".$stream."\nendstream";
        }
        $objects[2] = '<< /Type /Pages /Count '.count($pageIds).' /Kids ['.implode(' ', array_map(fn ($id) => $id.' 0 R', $pageIds)).'] >>';
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
        ksort($objects);
        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        $max = max(array_keys($objects));
        for ($id = 1; $id <= $max; $id++) {
            $offsets[$id] = strlen($pdf);
            $pdf .= $id." 0 obj\n".($objects[$id] ?? '<<>>')."\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".($max + 1)."\n0000000000 65535 f \n";
        for ($id = 1; $id <= $max; $id++) $pdf .= sprintf('%010d 00000 n ', $offsets[$id])."\n";
        return $pdf.'trailer << /Size '.($max + 1).' /Root 1 0 R >>'."\nstartxref\n{$xref}\n%%EOF";
    }

    /** Escapes legacy PDF text after conservative Windows-1252 transliteration. */
    private function pdfEscape(string $value): string
    {
        $encoded = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $value);
        return str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', ' ', ' '], $encoded !== false ? $encoded : $value);
    }

    /** Truncates a string safely when mbstring is available and conservatively otherwise. */
    private function truncate(string $value, int $length): string
    {
        if (function_exists('mb_strlen')) return mb_strlen($value) > $length ? mb_substr($value, 0, $length - 1).'…' : $value;
        return strlen($value) > $length ? substr($value, 0, $length - 1).'...' : $value;
    }

    /** Accepts only six-digit hexadecimal colors. */
    private function safeColor(mixed $value, string $default): string
    {
        return is_string($value) && preg_match('/^#[0-9A-Fa-f]{6}$/', $value) ? $value : $default;
    }

    /** Accepts a small font-family catalog and returns CSS-safe quoting. */
    private function safeFont(string $value): string
    {
        $allowed = ['Arial', 'Helvetica', 'Georgia', 'Times New Roman', 'Courier New', 'Noto Sans', 'Noto Sans Arabic'];
        $font = in_array($value, $allowed, true) ? $value : 'Arial';
        return str_contains($font, ' ') ? '"'.$font.'"' : $font;
    }

    /** Clamps numeric page margins into safe millimetre values. */
    private function mm(mixed $value): string
    {
        return number_format(min(45, max(5, (float) $value)), 1, '.', '').'mm';
    }

    /** HTML-escapes one scalar string. */
    private function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** HTML-escapes text and preserves author-requested line breaks. */
    private function eWithBreaks(string $value): string
    {
        return nl2br($this->e($value), false);
    }
}
