$(document).ready(function () {

    $('.iiif-copy').on('click', function(e) {
        const button = $(this);
        const iiifUrl = button.data('iiif-url');
        // Navigator clipboard requires a secure connection (https).
        if (navigator.clipboard) {
            navigator.clipboard
                .writeText(iiifUrl)
                .then(() => {
                    CommonDialog.dialogAlert({message: button.data('textCopied') || 'Url of the IIIF manifest copied in clipboard!'});
                })
                .catch(() => {
                    CommonDialog.dialogAlert({message: button.data('textFailed') || 'Unable to copy url in clipboard!'});
                });
        } else {
            CommonDialog.dialogAlert({message: iiifUrl});
        }
    });

});
