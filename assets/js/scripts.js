// Password strength meter
document.getElementById('password')?.addEventListener('input', function() {
    let pass = this.value;
    let strength = 0;
    if (pass.length > 8) strength++;
    if (pass.match(/[a-z]/)) strength++;
    if (pass.match(/[A-Z]/)) strength++;
    if (pass.match(/\d/)) strength++;
    if (pass.match(/[^a-zA-Z\d]/)) strength++;
    document.getElementById('password-strength').innerHTML = 'Strength: ' + strength + '/5';
});

// Highlight doc references in comments (on load)
document.querySelectorAll('.comments').forEach(el => {
    el.innerHTML = el.innerHTML.replace(/\$\{(.+?)\}/g, '<strong class="highlight">$0</strong>');
});