"""Renders listing/social-preview-1280x640.png (GitHub social preview) with headless Chrome."""
import shutil, subprocess, tempfile
from pathlib import Path
ROOT = Path(__file__).resolve().parent.parent
HTML = """<!doctype html><meta charset=utf-8><style>
html,body{margin:0;width:1280px;height:640px;background:#11151c;color:#e8edf5;font-family:'Noto Sans','DejaVu Sans',sans-serif;overflow:hidden}
.w{padding:70px 80px} h1{font-size:76px;margin:0;letter-spacing:-1px} h1 span{color:#57bfcc}
p{font-size:30px;color:#9aa8bd;margin:22px 0 0;max-width:1060px;line-height:1.35}
.code{margin-top:44px;font:24px 'DejaVu Sans Mono',monospace;background:#0b0e13;border:1px solid #2c3544;border-radius:14px;padding:22px 28px;color:#7fd197}
.by{position:absolute;bottom:40px;left:80px;font-size:24px;color:#7f8da3}
</style><div class=w><h1>Prevodka <span>Lite</span></h1>
<p>SEPA credit transfer files (ISO 20022 pain.001.001.09) for PHP: IBAN checks, Bulgarian transliteration, exact control sums.</p>
<div class=code>composer require pnscripts/prevodka-lite</div></div><div class=by>Free, MIT. By PN Scripts, pnscripts.com</div>"""
with tempfile.TemporaryDirectory() as t:
    page = Path(t) / "p.html"; page.write_text(HTML)
    out = ROOT / "listing/social-preview-1280x640.png"
    subprocess.run([shutil.which("google-chrome"), "--headless=new", "--disable-gpu", "--hide-scrollbars", f"--user-data-dir={t}/u",
                    "--force-device-scale-factor=1", "--window-size=1280,640", f"--screenshot={out}", page.as_uri()],
                   check=True, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    print(out)
