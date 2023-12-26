(function ($) {
    "use strict";

    // Spinner
    var spinner = function () {
        setTimeout(function () {
            if ($('#spinner').length > 0) {
                $('#spinner').removeClass('show');
                $('#spinner').addClass('d-none');
            }
        }, 2000);
    };
    spinner();

    // Sticky Navbar
    $(window).scroll(function () {
        if ($(this).scrollTop() > 45) {
            $('.navbar').addClass('navbar-fixed-top shadow-sm');
        } else {
            $('.navbar').removeClass('navbar-fixed-top shadow-sm');
        }
    });
    
    // Back to top button
    $(window).scroll(function () {
        if ($(this).scrollTop() > 100) {
            $('.back-to-top').fadeIn('slow');
        } else {
            $('.back-to-top').fadeOut('slow');
        }
    });
    $('.back-to-top').click(function () {
        $('html, body').animate({scrollTop: 0}, 1500, 'easeInOutExpo');
        return false;
    });

})(jQuery);

// Password validation
function checkPasswordStrength() {
    const regex = {
        number: /\d/,
        special_characters: /[~!@#$%^&*-_+=?><]/,
        alphabets: /[a-zA-Z]/,
    };

    const password = $('#password').val().trim();
    const { length } = password;
    const status = $('#password-strength-status');
    const signup = $('#signup');

    if (length < 6) {
        applyStatus('Weak (should be at least 6 characters.)', true);
    } else if (Object.values(regex).every(pattern => pattern.test(password))) {
        applyStatus('Strong', false);
    } else {
        applyStatus('Medium (should include alphabets, numbers, and special characters.)', true);
    }

    function applyStatus(text, isDisabled) {
        status.removeClass().addClass(`alert fw-bold ${isDisabled ? 'alert-danger' : 'alert-success'}`).html(text);
        signup.prop('disabled', isDisabled);
    }
}

function checkPasswordMatch() {
    const password = $('#password').val().trim();
    const confirmPassword = $('input[name="new_password2"]').val().trim();
    const status = $('#password-strength-status');

    if (password === confirmPassword && password.length > 0 && status.hasClass('alert-success')) {
        $('#password_submit').prop('disabled', false);
    } else {
        $('#password_submit').prop('disabled', true);
    }
}