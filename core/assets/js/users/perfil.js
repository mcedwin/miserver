$(document).ready(function(){
    $('.formu').load_img();
    $('.formu').submit(function() {
        $(this).mysave((data) => document.location = data.redirect);
        return false;
    });
})