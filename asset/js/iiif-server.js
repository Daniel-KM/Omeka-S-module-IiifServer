$(document).ready(function () {

    $('.iiif-copy').on('click', function(e) {
        navigator.clipboard.writeText($(this).data('iiifUrl'));
        alert('Url of the IIIF manifest copied in clipboard!');
    });

});
