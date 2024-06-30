$(document).ready(function() {
    $("form").on("submit", function(event) {
        var formId = $(this).attr('id');
        var username = $("#username").val();
        var email = $("#email").val();
        var password = $("#password").val();
        var error = "";

        // Centralized error message container
        var errorContainer = $(".error-container");
        errorContainer.html(""); // Clear previous error messages

        if (username.length < 5) {
            error = "Username must be at least 5 characters.";
        } else if (formId === "registerForm" && (!email || !validateEmail(email))) {
            error = "Invalid email address.";
        } else if (password.length < 8 || !/\d/.test(password)) {
            error = "Password must be at least 8 characters and include a number.";
        }

        if (error) {
            event.preventDefault();
            errorContainer.html('<div class="alert alert-danger">' + error + '</div>');
        }
    });

    function validateEmail(email) {
        var re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    }
});