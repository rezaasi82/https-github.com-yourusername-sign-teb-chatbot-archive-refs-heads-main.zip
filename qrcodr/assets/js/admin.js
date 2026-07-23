document.addEventListener('DOMContentLoaded', function () {
    const shortUrlEl = document.getElementById('qrcodr-short-url');
    const target = document.getElementById('qrcodr-canvas-target');
    const downloadBtn = document.getElementById('qrcodr-download-btn');

    if (!shortUrlEl || !target || typeof QRCode === 'undefined') {
        return;
    }

    const shortUrl = shortUrlEl.dataset.shortUrl;
    const sizeSelect = document.getElementById('qrcodr-size');
    const colorDarkInput = document.getElementById('qrcodr-color-dark');
    const colorLightInput = document.getElementById('qrcodr-color-light');

    let currentCanvas = null;

    function renderPreview() {
        target.innerHTML = '';
        const size = parseInt(sizeSelect ? sizeSelect.value : 300, 10);

        new QRCode(target, {
            text: shortUrl,
            width: size,
            height: size,
            colorDark: colorDarkInput ? colorDarkInput.value : '#000000',
            colorLight: colorLightInput ? colorLightInput.value : '#ffffff',
            correctLevel: QRCode.CorrectLevel.H
        });

        setTimeout(function () {
            currentCanvas = target.querySelector('canvas');
            if (downloadBtn) {
                downloadBtn.style.display = currentCanvas ? 'inline-block' : 'none';
            }
        }, 50);
    }

    [sizeSelect, colorDarkInput, colorLightInput].forEach(function (el) {
        if (el) {
            el.addEventListener('change', renderPreview);
        }
    });

    if (downloadBtn) {
        downloadBtn.addEventListener('click', function () {
            if (!currentCanvas) {
                return;
            }
            const a = document.createElement('a');
            a.href = currentCanvas.toDataURL('image/png');
            a.download = 'qrcodr-preview.png';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
        });
    }

    renderPreview();
});
