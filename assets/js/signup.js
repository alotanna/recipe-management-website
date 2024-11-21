function validateForm(event) {
    event.preventDefault();

    const email = document.getElementById('email');
    const fname = document.getElementById('fname');
    const lname = document.getElementById('lname');
    const password = document.getElementById('password');
    const confirmPassword = document.getElementById('confirmPassword');
    const fnameError = document.getElementById("FnameError");
    const lnameError = document.getElementById("LnameError");
    const emailError = document.getElementById('emailError');
    const passwordError = document.getElementById('passwordError');
    const confirmPasswordError = document.getElementById('confirmPasswordError');

    // Reset error messages
    [fnameError, lnameError, emailError, passwordError, confirmPasswordError].forEach(error => {
        error.style.display = 'none';
    });

    let isValid = true;

    // First name validation
    if (fname.value.trim() === '') {
        fnameError.textContent = 'First name is required.';
        fnameError.style.display = 'block';
        isValid = false;
    } else if (!/^[a-zA-Z-' ]*$/.test(fname.value.trim())) {
        fnameError.textContent = 'Only letters and white space allowed.';
        fnameError.style.display = 'block';
        isValid = false;
    }

    // Last name validation
    if (lname.value.trim() === '') {
        lnameError.textContent = 'Last name is required.';
        lnameError.style.display = 'block';
        isValid = false;
    } else if (!/^[a-zA-Z-' ]*$/.test(lname.value.trim())) {
        lnameError.textContent = 'Only letters and white space allowed.';
        lnameError.style.display = 'block';
        isValid = false;
    }

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
        passwordError.textContent = 'Password must be at least 8 characters long, contain at least one uppercase letter, at least three digits, and at least one special character.';
        passwordError.style.display = 'block';
        isValid = false;
    }

    // Confirm password validation
    if (password.value !== confirmPassword.value) {
        confirmPasswordError.textContent = 'Passwords do not match.';
        confirmPasswordError.style.display = 'block';
        isValid = false;
    }

    if (isValid) {
        // If all validations pass, submit the form
        document.getElementById('signupForm').submit();
    }

    return false;
}