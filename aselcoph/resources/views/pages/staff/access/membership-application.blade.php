<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>ASELCO Membership Application — {{ $user->name }}</title>
<style>
  @page {
    size: letter;
    margin: 0.4in;
  }
  * { box-sizing: border-box; }
  body {
    font-family: Georgia, 'Times New Roman', serif;
    color: #111;
    margin: 0;
    padding: 20px;
    background: #eee;
  }
  .page {
    background: #fff;
    border: 2px solid #000;
    max-width: 850px;
    margin: 0 auto 30px auto;
    padding: 22px 30px;
  }
  .header { text-align: center; margin-bottom: 10px; }
  .header h1 { font-size: 15px; margin: 0; letter-spacing: 0.5px; }
  .header .sub { font-size: 12px; margin: 2px 0 10px 0; }
  .header .title {
    font-size: 13px;
    font-weight: bold;
    margin: 10px 0 14px 0;
    text-transform: uppercase;
  }
  .intro {
    font-size: 12.5px;
    text-align: justify;
    margin-bottom: 10px;
    line-height: 1.5;
  }
  .intro .fillline {
    border-bottom: 1px solid #000;
    padding: 0 4px;
    font-weight: bold;
  }
  ol.terms {
    font-size: 12.5px;
    padding-left: 22px;
    margin: 0 0 14px 0;
    text-align: justify;
    line-height: 1.5;
  }
  ol.terms li { margin-bottom: 6px; }
  .consent-box {
    border: 1.5px solid #000;
    padding: 14px 18px;
    margin-bottom: 20px;
  }
  .consent-box .cbtitle {
    text-align: center;
    font-weight: bold;
    font-size: 13px;
    margin-bottom: 10px;
    text-decoration: underline;
  }
  .consent-box p {
    font-size: 12.5px;
    text-align: justify;
    line-height: 1.5;
    margin: 6px 0;
  }
  .sig-line {
    text-align: right;
    margin-top: 16px;
    display: flex;
    flex-direction: column;
    align-items: flex-end;
  }
  .sig-line .name-input {
    font-weight: bold;
    text-transform: uppercase;
    border: none;
    border-bottom: 1px solid #000;
    text-align: center;
    font-size: 13px;
    font-family: Georgia, serif;
    width: 260px;
    padding: 2px;
    background: transparent;
  }
  .sig-line .caption { font-size: 10.5px; margin-top: 2px; }
  .id-row {
    display: flex;
    align-items: flex-start;
    gap: 20px;
    margin-bottom: 18px;
    padding-bottom: 14px;
    border-bottom: 1px solid #000;
  }
  .photo-box {
    width: 110px;
    height: 130px;
    border: 1px solid #000;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 10px;
    color: #888;
    text-align: center;
    overflow: hidden;
    flex-shrink: 0;
    position: relative;
    background: #fff;
  }
  .photo-box img {
    width: 100%;
    height: 100%;
    object-fit: cover;
  }
  .photo-box .photo-placeholder {
    padding: 8px;
    line-height: 1.35;
  }
  .photo-actions {
    position: absolute;
    left: 0;
    right: 0;
    bottom: 0;
    display: flex;
    gap: 2px;
    padding: 4px;
    background: linear-gradient(transparent, rgba(0,0,0,.72));
  }
  .photo-actions button {
    flex: 1;
    font-family: Arial, sans-serif;
    font-size: 9px;
    font-weight: 700;
    letter-spacing: .02em;
    text-transform: uppercase;
    border: 0;
    border-radius: 3px;
    padding: 5px 2px;
    cursor: pointer;
    color: #fff;
    background: rgb(14 124 58);
  }
  .photo-actions button.ghost {
    background: rgba(255,255,255,.18);
  }
  .sig-box {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
  }
  .sig-canvas-wrap {
    width: 260px;
    height: 90px;
    border: 1px solid #000;
    position: relative;
    background: #fff;
  }
  .sig-canvas-wrap.consent {
    width: 260px;
    height: 72px;
    margin-bottom: 6px;
  }
  .sig-canvas-wrap canvas {
    display: block;
    width: 100%;
    height: 100%;
    touch-action: none;
    cursor: crosshair;
  }
  .sig-hint {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #aaa;
    font-size: 11px;
    pointer-events: none;
  }
  .sig-clear {
    margin-top: 4px;
    font-family: Arial, sans-serif;
    font-size: 10px;
    background: none;
    border: 0;
    color: #555;
    cursor: pointer;
    text-decoration: underline;
  }
  .sig-box .applicant-name {
    margin-top: 6px;
    font-weight: bold;
    text-transform: uppercase;
    font-family: Georgia, serif;
    font-size: 13px;
    width: 240px;
    padding: 2px;
    text-align: center;
    border: none;
    border-bottom: 1px solid #000;
    background: transparent;
  }
  .sig-box .caption { font-size: 10.5px; margin-top: 2px; }
  .barcode-box {
    width: 168px;
    text-align: right;
    flex-shrink: 0;
  }
  .barcode-box svg {
    width: 100%;
    height: 60px;
    display: block;
  }
  .barcode-box .code {
    font-size: 11px;
    letter-spacing: 1px;
    margin-top: 2px;
  }
  .barcode-box input {
    width: 100%;
    text-align: right;
    border: none;
    font-size: 11px;
    font-family: Georgia, serif;
    background: transparent;
  }
  .info-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    column-gap: 30px;
    row-gap: 10px;
    font-size: 12.5px;
  }
  .field { display: flex; align-items: baseline; gap: 6px; }
  .field label { font-weight: bold; white-space: nowrap; }
  .field input[type=text],
  .field input[type=date] {
    flex: 1;
    border: none;
    border-bottom: 1px solid #000;
    font-family: Georgia, serif;
    font-size: 12.5px;
    padding: 2px 4px;
    background: transparent;
  }
  .field.remarks { grid-column: 1 / 2; align-items: flex-start; }
  .toolbar {
    max-width: 850px;
    margin: 0 auto 14px auto;
    display: flex;
    justify-content: flex-end;
    gap: 8px;
  }
  .toolbar a,
  .toolbar button {
    font-family: Arial, sans-serif;
    font-size: 13px;
    padding: 8px 16px;
    background: #1a5c2e;
    color: #fff;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
  }
  .toolbar a.secondary { background: #555; }
  .toolbar button:hover,
  .toolbar a:hover { background: #134a24; }
  .toolbar a.secondary:hover { background: #333; }
  .camera-modal {
    position: fixed;
    inset: 0;
    z-index: 40;
    background: rgba(12, 18, 14, .62);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
  }
  .camera-modal[hidden] { display: none !important; }
  .camera-card {
    width: min(420px, 100%);
    background: #fff;
    border-radius: 16px;
    padding: 16px;
    font-family: Arial, sans-serif;
    box-shadow: 0 20px 50px rgba(0,0,0,.28);
  }
  .camera-card h2 {
    margin: 0 0 10px;
    font-size: 16px;
  }
  .camera-card video {
    width: 100%;
    height: 280px;
    object-fit: cover;
    background: #111;
    border-radius: 10px;
  }
  .camera-card .row {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    margin-top: 12px;
  }
  .camera-card button {
    border: 0;
    border-radius: 8px;
    padding: 8px 14px;
    cursor: pointer;
    font-size: 13px;
  }
  .camera-card .cancel { background: #eee; }
  .camera-card .snap { background: #0e7c3a; color: #fff; }
  @media print {
    body { background: #fff; padding: 0; }
    .page { border: none; margin: 0; box-shadow: none; }
    .no-print { display: none !important; }
  }
</style>
</head>
<body>
@php
    $profile = $profile ?? null;
    $accountLink = $accountLink ?? null;
    $applicantName = strtoupper($accountLink?->owner_name ?: $user->name);
    $accountNumber = $accountLink?->account_number ?: '';
    $hasPhoto = filled($user->profile_photo_path);
    $photoUrl = $hasPhoto
        ? asset('storage/'.ltrim((string) $user->profile_photo_path, '/'))
        : null;
    $contact = $profile?->contact_no ?: $user->contact_no;
@endphp

<div class="toolbar no-print">
  <a class="secondary" href="{{ route('access.customers.show', $user) }}">Back to customer</a>
  <button type="button" onclick="window.print()">Print / Save as PDF</button>
</div>

<div class="page">
  <div class="header">
    <h1>AGUSAN DEL SUR ELECTRIC COOPERATIVE, INC.</h1>
    <div class="sub">SAN FRANCISCO, AGUSAN DEL SUR</div>
    <div class="title">Application for Juridical, Joint, and Single Membership and for Electric Service</div>
  </div>

  <div class="intro">
    The undersigned herein after called the APPLICANT hereby applies for membership and agrees to purchase electric
    energy from the <span class="fillline">&nbsp;AGUSAN DEL SUR ELECTRIC COOPERATIVE, INC. (ASELCO)&nbsp;</span>
    herein-after called the COOPERATIVE upon the following terms and conditions:
  </div>

  <ol class="terms">
    <li>The Applicant will pay to the Cooperative the sum of <strong>FIVE PESOS (5.00)</strong> which, if this application is accepted, will constitute the Applicant's Membership Fee.</li>
    <li>The Applicant will, when electric energy becomes available, purchase from the Cooperative all electric energy for use on the premises described below and will pay therefore monthly rates to be determined from time to time in accordance with the policies established by the Cooperative's Board of Directors.</li>
    <li>The Applicant will cause this premises to be wired in accordance with the wiring specifications approved by the Cooperative.</li>
    <li>The Applicant will comply and be bound by the provisions of the Charter and By-laws of the Cooperative, and such Rules and Regulations as may from time to time be adopted by the Cooperative.</li>
    <li>The Applicant by paying a Membership Fee and becoming a member assumes no personal liabilities or responsibilities of the Cooperative, and it is expressly understood that under the law, his private property is exempt from execution for any debt or liability.</li>
    <li>The undersigned will grant to the Cooperative at its request the necessary rights and easement to construct, operate, replace, repair and perpetually maintain on the property owned by the undersigned, and in or upon all roads, streets and highways abutting said property, its lines for transmission or distribution of electric energy and will execute and deliver to the cooperative any conveyance, grant or instrument which the cooperative shall deemed necessary or convenient for the said purposes, or any of them. All lines of the Cooperative and switches, meters, and other appliances and equipments constructed or installed by the Cooperative on said property shall at all times be sole property of the Cooperative and the said Cooperative shall have rights or access to said property to operate, maintain or relocate its facilities.</li>
    <li>The Cooperative shall not be liable in damages to the Applicant for failure to supple electricity to said premises under any condition.</li>
    <li>The Acceptance of this Application by cooperative shall be considered an agreement between the Applicant and the Cooperative as the Contract for Electric Service shall continue in force for one year from the date service is made available by the Cooperative to the Applicant and thereafter the Applicant has need for any electric service on the subject premises.</li>
  </ol>

  <div class="consent-box">
    <div class="cbtitle">DATA PRIVACY CONSENT FORM</div>
    <p>I have read the AGUSAN DEL SUR Electric Cooperative, Inc's <strong>Data Privacy Statement</strong> and hereby allow the Organization to collect, use, process and store my personal information through its official channels for legitimate purposes.</p>
    <p>I affirm my fundamental right to privacy and my constitutional data privacy rights as stated in the Republic Act No. 10173 of the Philippines. This consent is hereby given on the guarantee that these rights shall be upheld at all times.</p>
    <div class="sig-line">
      <div class="sig-canvas-wrap consent">
        <canvas id="consentPad" width="260" height="72"></canvas>
        <span class="sig-hint" id="consentHint">Sign here</span>
      </div>
      <button type="button" class="sig-clear no-print" data-clear-pad="consentPad">Clear signature</button>
      <input type="text" class="name-input" value="{{ $applicantName }}">
      <div class="caption">Signature Over Printed Name</div>
    </div>
  </div>

  <div class="id-row">
    <div class="photo-box" id="photoBox">
      <span class="photo-placeholder" id="photoPlaceholder" @if($photoUrl) hidden @endif>1x1 photo</span>
      <img id="photoPreview" alt="{{ $user->name }}" @if($photoUrl) src="{{ $photoUrl }}" @else hidden @endif>
      <div class="photo-actions no-print">
        <button type="button" class="ghost" id="photoUploadBtn">Upload</button>
        <button type="button" id="photoCameraBtn">Camera</button>
      </div>
      <input id="photoFile" type="file" accept="image/*" hidden>
      <input id="photoCapture" type="file" accept="image/*" capture="user" hidden>
    </div>
    <div class="sig-box">
      <div class="sig-canvas-wrap">
        <canvas id="applicantPad" width="260" height="90"></canvas>
        <span class="sig-hint" id="applicantHint">Sign here</span>
      </div>
      <button type="button" class="sig-clear no-print" data-clear-pad="applicantPad">Clear signature</button>
      <input type="text" class="applicant-name" value="{{ $applicantName }}">
      <div class="caption">(Applicant's Name)</div>
    </div>
    <div class="barcode-box">
      <svg viewBox="0 0 168 60" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
        <g id="barcodeLines" fill="#000"></g>
      </svg>
      <div class="code">
        <input id="barcodeValue" type="text" value="{{ $accountNumber }}" autocomplete="off">
      </div>
    </div>
  </div>

  <div class="info-grid">
    <div class="field">
      <label>Address :</label>
      <input type="text" value="{{ $profile?->address }}">
    </div>
    <div class="field">
      <label>Street :</label>
      <input type="text" value="{{ $profile?->street }}">
    </div>
    <div class="field">
      <label>Sitio :</label>
      <input type="text" value="{{ $profile?->sitio }}">
    </div>
    <div class="field">
      <label>Municipality :</label>
      <input type="text" value="{{ $profile?->city_municipality_name }}">
    </div>
    <div class="field">
      <label>Barangay :</label>
      <input type="text" value="{{ $profile?->barangay_name }}">
    </div>
    <div class="field">
      <label>Date Issued :</label>
      <input type="date">
    </div>
    <div class="field">
      <label>Civil Status :</label>
      <input type="text" value="{{ $profile?->civilStatusLabel() }}">
    </div>
    <div class="field">
      <label>Contact # :</label>
      <input type="text" value="{{ $contact }}">
    </div>
    <div class="field">
      <label>Membership O.R.# :</label>
      <input type="text">
    </div>
    <div class="field">
      <label>Area Manager's Signature :</label>
      <input type="text">
    </div>
    <div class="field">
      <label>Sex :</label>
      <input type="text" value="{{ $profile?->sexLabel() }}">
    </div>
    <div></div>
    <div class="field">
      <label>Date of Seminar :</label>
      <input type="date" value="{{ $profile?->date_of_seminar?->toDateString() }}">
    </div>
    <div></div>
    <div class="field remarks">
      <label>Remarks :</label>
      <input type="text" style="width:100%;" value="{{ $profile?->remarks }}">
    </div>
  </div>
</div>

<div id="cameraModal" class="camera-modal no-print" hidden>
  <div class="camera-card" role="dialog" aria-modal="true" aria-labelledby="cameraTitle">
    <h2 id="cameraTitle">Take 1×1 photo</h2>
    <video id="cameraVideo" autoplay playsinline></video>
    <div class="row">
      <button type="button" class="cancel" id="cameraCancel">Cancel</button>
      <button type="button" class="snap" id="cameraSnap">Capture</button>
    </div>
  </div>
</div>

<script>
  (function () {
    const CODE39 = {
      '0': 'nnnwwnwnn', '1': 'wnnwnnnnw', '2': 'nnwwnnnnw', '3': 'wnwwnnnnn',
      '4': 'nnnwwnnnw', '5': 'wnnwwnnnn', '6': 'nnwwwnnnn', '7': 'nnnwnnwnw',
      '8': 'wnnwnnwnn', '9': 'nnwwnnwnn',
      'A': 'wnnnnwnnw', 'B': 'nnwnnwnnw', 'C': 'wnwnnwnnn', 'D': 'nnnnwwnnw',
      'E': 'wnnnwwnnn', 'F': 'nnwnwwnnn', 'G': 'nnnnnwwnw', 'H': 'wnnnnwwnn',
      'I': 'nnwnnwwnn', 'J': 'nnnnwwwnn', 'K': 'wnnnnnnww', 'L': 'nnwnnnnww',
      'M': 'wnwnnnnwn', 'N': 'nnnnwnnww', 'O': 'wnnnwnnwn', 'P': 'nnwnwnnwn',
      'Q': 'nnnnnnwww', 'R': 'wnnnnnwwn', 'S': 'nnwnnnwwn', 'T': 'nnnnwnwwn',
      'U': 'wwnnnnnnw', 'V': 'nwwnnnnnw', 'W': 'wwwnnnnnn', 'X': 'nwnnwnnnw',
      'Y': 'wwnnwnnnn', 'Z': 'nwwnwnnnn',
      '-': 'nwnnnnwnw', '.': 'wwnnnnwnn', ' ': 'nwwnnnwnn', '*': 'nwnnwnwnn',
    };

    function barcodeModules(value) {
      const raw = String(value || '').toUpperCase().replace(/[^0-9A-Z\-\. ]/g, '');
      const text = '*' + (raw || '0') + '*';
      const modules = [];
      [...text].forEach((ch, index) => {
        const pattern = CODE39[ch] || CODE39['0'];
        [...pattern].forEach((bit, i) => {
          modules.push({ bar: i % 2 === 0, w: bit === 'w' ? 3 : 1 });
        });
        if (index < text.length - 1) {
          modules.push({ bar: false, w: 1 });
        }
      });
      return modules;
    }

    function drawBarcode(value) {
      const g = document.getElementById('barcodeLines');
      if (!g) return;
      while (g.firstChild) g.removeChild(g.firstChild);
      const modules = barcodeModules(value);
      const total = modules.reduce((sum, m) => sum + m.w, 0);
      const viewW = 168;
      const viewH = 60;
      const quiet = 4;
      const unit = Math.min(1.35, (viewW - quiet * 2) / total);
      let x = viewW - quiet;
      for (let i = modules.length - 1; i >= 0; i -= 1) {
        const m = modules[i];
        x -= m.w * unit;
        if (!m.bar) continue;
        const rect = document.createElementNS('http://www.w3.org/2000/svg', 'rect');
        rect.setAttribute('x', x.toFixed(2));
        rect.setAttribute('y', '4');
        rect.setAttribute('width', (m.w * unit).toFixed(2));
        rect.setAttribute('height', String(viewH - 14));
        g.appendChild(rect);
      }
    }

    const pads = {};
    function bindPad(canvas, hint) {
      const ctx = canvas.getContext('2d');
      const cssW = canvas.clientWidth || canvas.width;
      const cssH = canvas.clientHeight || canvas.height;
      const ratio = Math.max(window.devicePixelRatio || 1, 1);
      canvas.width = cssW * ratio;
      canvas.height = cssH * ratio;
      ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
      ctx.lineWidth = 1.8;
      ctx.lineCap = 'round';
      ctx.lineJoin = 'round';
      ctx.strokeStyle = '#111';
      let drawing = false;
      const pos = (event) => {
        const rect = canvas.getBoundingClientRect();
        return { x: event.clientX - rect.left, y: event.clientY - rect.top };
      };
      canvas.addEventListener('pointerdown', (event) => {
        event.preventDefault();
        canvas.setPointerCapture(event.pointerId);
        drawing = true;
        const p = pos(event);
        ctx.beginPath();
        ctx.moveTo(p.x, p.y);
        if (hint) hint.hidden = true;
      });
      canvas.addEventListener('pointermove', (event) => {
        if (!drawing) return;
        event.preventDefault();
        const p = pos(event);
        ctx.lineTo(p.x, p.y);
        ctx.stroke();
      });
      const stop = () => { drawing = false; };
      canvas.addEventListener('pointerup', stop);
      canvas.addEventListener('pointercancel', stop);
      pads[canvas.id] = () => {
        ctx.clearRect(0, 0, cssW, cssH);
        if (hint) hint.hidden = false;
      };
    }

    bindPad(document.getElementById('consentPad'), document.getElementById('consentHint'));
    bindPad(document.getElementById('applicantPad'), document.getElementById('applicantHint'));
    document.querySelectorAll('[data-clear-pad]').forEach((btn) => {
      btn.addEventListener('click', () => pads[btn.dataset.clearPad]?.());
    });

    const preview = document.getElementById('photoPreview');
    const placeholder = document.getElementById('photoPlaceholder');
    const fileInput = document.getElementById('photoFile');
    const captureInput = document.getElementById('photoCapture');
    function showPhoto(src) {
      preview.src = src;
      preview.hidden = false;
      placeholder.hidden = true;
    }
    function readFile(input) {
      const file = input.files && input.files[0];
      if (!file) return;
      const reader = new FileReader();
      reader.onload = (event) => showPhoto(String(event.target.result || ''));
      reader.readAsDataURL(file);
      input.value = '';
    }
    document.getElementById('photoUploadBtn').addEventListener('click', () => fileInput.click());
    document.getElementById('photoCameraBtn').addEventListener('click', async () => {
      if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        captureInput.click();
        return;
      }
      try {
        const stream = await navigator.mediaDevices.getUserMedia({
          video: { facingMode: 'user', width: { ideal: 720 }, height: { ideal: 720 } },
          audio: false,
        });
        openCamera(stream);
      } catch (err) {
        captureInput.click();
      }
    });
    fileInput.addEventListener('change', () => readFile(fileInput));
    captureInput.addEventListener('change', () => readFile(captureInput));

    const modal = document.getElementById('cameraModal');
    const video = document.getElementById('cameraVideo');
    let cameraStream = null;
    function openCamera(stream) {
      cameraStream = stream;
      video.srcObject = stream;
      modal.hidden = false;
    }
    function closeCamera() {
      modal.hidden = true;
      if (cameraStream) {
        cameraStream.getTracks().forEach((track) => track.stop());
        cameraStream = null;
      }
      video.srcObject = null;
    }
    document.getElementById('cameraCancel').addEventListener('click', closeCamera);
    document.getElementById('cameraSnap').addEventListener('click', () => {
      const canvas = document.createElement('canvas');
      const size = 400;
      canvas.width = size;
      canvas.height = size;
      const ctx = canvas.getContext('2d');
      const vw = video.videoWidth || size;
      const vh = video.videoHeight || size;
      const side = Math.min(vw, vh);
      const sx = (vw - side) / 2;
      const sy = (vh - side) / 2;
      ctx.drawImage(video, sx, sy, side, side, 0, 0, size, size);
      showPhoto(canvas.toDataURL('image/jpeg', 0.9));
      closeCamera();
    });

    const barcodeInput = document.getElementById('barcodeValue');
    drawBarcode(barcodeInput.value);
    barcodeInput.addEventListener('input', () => drawBarcode(barcodeInput.value));
  })();
</script>
</body>
</html>
