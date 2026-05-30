"use strict";

const form = document.getElementById("auth-form");
const usernameInput = document.getElementById("auth-username");
const passwordInput = document.getElementById("auth-password");
const submitBtn = document.getElementById("auth-submit");
const togglePasswordBtn = document.getElementById("auth-toggle-password");
const eyeOpen = document.getElementById("auth-eye-open");
const eyeClosed = document.getElementById("auth-eye-closed");
const alertError = document.getElementById("auth-alert");
const alertMessage = document.getElementById("auth-alert-message");
const validation = {
  username: false,
  password: false,
};
const usernameError = document.getElementById("auth-username-error");
const usernameValid = document.getElementById("auth-username-valid");

function validateUsername() {
  const value = usernameInput.value.trim();
  const isValid = value.length >= 3;
  validation.username = isValid;

  if (value.length > 0) {
    if (isValid) {
      usernameInput.classList.remove("error");
      usernameInput.classList.add("success");
      usernameError.classList.remove("show");
      usernameValid.classList.add("valid");
      usernameValid.classList.remove("invalid");
    } else {
      usernameInput.classList.remove("success");
      usernameInput.classList.add("error");
      usernameError.classList.add("show");
      usernameValid.classList.remove("valid");
      usernameValid.classList.add("invalid");
    }
  } else {
    usernameInput.classList.remove("success", "error");
    usernameError.classList.remove("show");
    usernameValid.classList.remove("valid", "invalid");
  }
  updateSubmitButton();
}

function validatePassword() {
  const value = passwordInput.value;
  const isValid = value.length >= 6;
  validation.password = isValid;

  if (value.length > 0) {
    if (isValid) {
      passwordInput.classList.remove("error");
      passwordInput.classList.add("success");
      document.getElementById("auth-password-error").classList.remove("show");
    } else {
      passwordInput.classList.remove("success");
      passwordInput.classList.add("error");
      document.getElementById("auth-password-error").classList.add("show");
    }
  } else {
    passwordInput.classList.remove("success", "error");
    document.getElementById("auth-password-error").classList.remove("show");
  }
  updateSubmitButton();
}

function updateSubmitButton() {
  submitBtn.disabled = !(validation.username && validation.password);
}

function togglePassword() {
  const isPassword = passwordInput.type === "password";
  passwordInput.type = isPassword ? "text" : "password";
  eyeOpen.style.display = isPassword ? "none" : "block";
  eyeClosed.style.display = isPassword ? "block" : "none";
}

function showError(message) {
  alertMessage.textContent = message;
  alertError.classList.add("show");
}

function hideError() {
  alertError.classList.remove("show");
}

usernameInput.addEventListener("input", validateUsername);
usernameInput.addEventListener("blur", validateUsername);
passwordInput.addEventListener("input", validatePassword);
passwordInput.addEventListener("blur", validatePassword);
togglePasswordBtn.addEventListener("click", togglePassword);

form.addEventListener("submit", function (e) {
  validateUsername();
  validatePassword();
  if (!(validation.username && validation.password)) {
    e.preventDefault();
    return;
  }
  submitBtn.classList.add("loading");
  submitBtn.disabled = true;
  hideError();
});

// Mostrar error desde URL
const urlParams = new URLSearchParams(window.location.search);
if (urlParams.has("error")) {
  const errorType = urlParams.get("error");
  let message = "Credenciales inválidas. Por favor, intenta de nuevo.";
  if (errorType === "csrf")
    message = "Sesión expirada. Por favor, intenta de nuevo.";
  else if (errorType === "empty")
    message = "Por favor, completá todos los campos.";
  else if (errorType === "inactive")
    message = "Tu cuenta está inactiva. Contactá al administrador.";
  showError(message);
}

usernameInput.addEventListener("input", hideError);
passwordInput.addEventListener("input", hideError);
