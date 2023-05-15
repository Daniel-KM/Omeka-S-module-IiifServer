$(document).ready(function () {

    $('.iiif-copy').on('click', function(e) {
        const button = $(this);
        navigator.clipboard.writeText(button.data('iiif-url'));
        alert(button.data('textCopied') ? button.data('textCopied') : 'Url of the IIIF manifest copied in clipboard!');
    });

});
