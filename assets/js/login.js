function validateForm(event) {
    event.preventDefault();

    const email = document.getElementById('email');
    const password = document.getElementById('password');
    const emailError = document.getElementById('emailError');
    const passwordError = document.getElementById('passwordError');

    // Reset error messages
    emailError.style.display = 'none';
    passwordError.style.display = 'none';

    let isValid = true;

    // Email validation
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email.value)) {
        emailError.textContent = 'Please enter a valid email address.';
        emailError.style.display = 'block';
        isValid = false;
    }

    // Password validation
    const passwordRegex = /^(?=.*[A-Z])(?=.*\d{3,})(?=.*[!@#$%^&*])[A-Za-z\d!@#$%^&*]{8,}$/;
    if (!passwordRegex.test(password.value)) {
        passwordError.textContent = 'Password must be at least 8 characters long, contain at least one uppercase, at least three digits, and at least one special character.';
        passwordError.style.display = 'block';
        isValid = false;
    }

    if (isValid) {
        // If all validations pass, submit the form
        document.getElementById('loginForm').submit();
    }

    return false;
}