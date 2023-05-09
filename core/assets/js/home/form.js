$(document).ready(function() {
    $('.form-login').submit(function() {
        $(this).mysave((data) => document.location = data.redirect);
        return false;
    });
})