document.addEventListener('DOMContentLoaded', function () {
    if (typeof QRCode === 'undefined') {
        return;
    }

    document.querySelectorAll('.qrcodr-frontend').forEach(function (container) {
        const target = container.querySelector('.qrcodr-frontend-canvas');
        if (!target) {
            return;
        }

        const size = parseInt(container.dataset.size, 10) || 300;

        new QRCode(target, {
            text: container.dataset.shortUrl,
            width: size,
            height: size,
            colorDark: container.dataset.colorDark || '#000000',
            colorLight: container.dataset.colorLight || '#ffffff',
            correctLevel: QRCode.CorrectLevel.H
        });

        const downloadBtn = container.querySelector('.qrcodr-frontend-download');
        if (!downloadBtn || container.dataset.download !== '1') {
            return;
        }

        setTimeout(function () {
            const canvas = target.querySelector('canvas');
            if (!canvas) {
                return;
            }
            downloadBtn.style.display = 'inline-block';
            downloadBtn.addEventListener('click', function () {
                const a = document.createElement('a');
                a.href = canvas.toDataURL('image/png');
                a.download = 'qrcodr.png';
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
            });
        }, 50);
    });
});
