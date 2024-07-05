$(document).ready(function(){
    $('.formu').submit(function() {
        $(this).mysave((data) => document.location = data.redirect);
        return false;
    });
})