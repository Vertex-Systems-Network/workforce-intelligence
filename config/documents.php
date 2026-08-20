<?php

return [
    'pdf_driver' => env('DOCUMENT_PDF_DRIVER', 'auto'),
    'chromium_binary' => env('DOCUMENT_CHROMIUM_BINARY'),
    'render_timeout_seconds' => (int) env('DOCUMENT_RENDER_TIMEOUT', 35),
    'max_embedded_image_bytes' => (int) env('DOCUMENT_MAX_EMBEDDED_IMAGE_BYTES', 5 * 1024 * 1024),
    'code_python_binary' => env('DOCUMENT_CODE_PYTHON_BINARY'),
];
