$(document).ready(function () {

    $('.iiif-copy').on('click', function(e) {
        const button = $(this);
        const iiifUrl = button.data('iiif-url');
        // Navigator clipboard requires a secure connection (https).
        if (navigator.clipboard) {
            navigator.clipboard
                .writeText(iiifUrl)
                .then(() => {
                    alert(button.data('textCopied') ? button.data('textCopied') : 'Url of the IIIF manifest copied in clipboard!');
                })
                .catch(() => {
                    alert(button.data('textFailed') ? button.data('textFailed') : 'Unable to copy url in clipboard!');
                });
        } else {
            alert(iiifUrl);
        }
    });

});
