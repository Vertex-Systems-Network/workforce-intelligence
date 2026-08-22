#!/usr/bin/env python3
"""Rebuild deployment release ZIPs and storage/app/releases/manifest.json."""
from pathlib import Path
from datetime import datetime, timezone
import zipfile, hashlib, json, re
root=Path(__file__).resolve().parents[1]; out=root/'storage/app/releases'; out.mkdir(parents=True,exist_ok=True)
agent_source=(root/'desktop-agent/native-agent.mjs').read_text(encoding='utf-8')
agent_match=re.search(r"const VERSION = ['\"]([^'\"]+)['\"]",agent_source)
if not agent_match: raise RuntimeError('Could not determine native agent VERSION.')
agent_version=agent_match.group(1); browser_version='1.0.0'
for p in out.glob('*.zip'): p.unlink()
def z(name,entries):
 p=out/name
 with zipfile.ZipFile(p,'w',zipfile.ZIP_DEFLATED) as archive:
  for src,arc in entries:
   src=root/src
   if src.is_dir():
    for f in src.rglob('*'):
     if f.is_file(): archive.write(f,str(Path(arc)/f.relative_to(src)).replace('\\','/'))
   else: archive.write(src,arc)
 return p
common=[(Path('desktop-agent/native-agent.mjs'),'desktop-agent/native-agent.mjs'),(Path('desktop-agent/PRODUCTION_AGENT.md'),'desktop-agent/PRODUCTION_AGENT.md')]
w=z(f'WorkIntel-Agent-Windows-{agent_version}.zip',common+[(Path('desktop-agent/installers/windows'),'desktop-agent/installers/windows')])
m=z(f'WorkIntel-Agent-macOS-{agent_version}.zip',common+[(Path('desktop-agent/installers/macos'),'desktop-agent/installers/macos')])
l=z(f'WorkIntel-Agent-Linux-{agent_version}.zip',common+[(Path('desktop-agent/installers/linux'),'desktop-agent/installers/linux')])
c=z(f'WorkIntel-Browser-Chrome-Edge-{browser_version}.zip',[(Path('browser-extension')/n,n) for n in ['manifest.json','service-worker.js','popup.html','popup.js','popup.css','README.md']])
f=z(f'WorkIntel-Browser-Firefox-{browser_version}.zip',[(Path('browser-extension/firefox')/n,n) for n in ['manifest.json','service-worker.js','popup.html','popup.js','popup.css']]+[(Path('browser-extension/README.md'),'README.md')])
def row(slug,platform,kind,p,version,requirements,notes,guide_key):
 b=p.read_bytes();return {'slug':slug,'platform':platform,'kind':kind,'channel':'stable','version':version,'released_at':datetime.now(timezone.utc).isoformat().replace('+00:00','Z'),'requirements':requirements,'filename':p.name,'file':p.name,'size_bytes':len(b),'sha256':hashlib.sha256(b).hexdigest(),'mime_type':'application/zip','notes':notes,'guide_key':guide_key}
rows=[row('agent-windows-x64','Windows 10/11','agent',w,agent_version,'Windows 10/11, Node.js 20+, PowerShell 5+, curl','Per-user Scheduled Task installer with persistent state and managed self-update supervisor.','windows-agent'),row('agent-macos','macOS','agent',m,agent_version,'macOS 12+, Node.js 20+, curl','Per-user LaunchAgent installer with managed self-update.','macos-agent'),row('agent-linux','Linux','agent',l,agent_version,'Modern Linux desktop, Node.js 20+, curl, systemd user session','systemd-user installer with managed self-update.','linux-agent'),row('browser-chrome-edge','Chrome / Microsoft Edge','extension',c,browser_version,'Chromium Manifest V3','Domain-only browser tracker package.','chrome-edge-extension'),row('browser-firefox','Mozilla Firefox','extension',f,browser_version,'Firefox 128+','Firefox Manifest V3 domain-only tracker package.','firefox-extension')]
(out/'manifest.json').write_text(json.dumps({'version':2,'generated_at':datetime.now(timezone.utc).isoformat().replace('+00:00','Z'),'releases':rows},indent=2)+'\n')
(out/'SHA256SUMS.txt').write_text(''.join(f"{r['sha256']}  {r['filename']}\n" for r in rows))
print(f'Built {len(rows)} release packages in {out}')
