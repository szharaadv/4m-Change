// 4M Change — App JS

// Image preview on file input
document.addEventListener('change', function (e) {
    const input = e.target;
    if (!input.dataset.preview) return;
    const preview = document.getElementById(input.dataset.preview);
    const label   = document.getElementById(input.dataset.label);
    if (!preview || !input.files[0]) return;

    const reader = new FileReader();
    reader.onload = function (ev) {
        preview.src = ev.target.result;
        preview.style.display = 'block';
    };
    reader.readAsDataURL(input.files[0]);

    if (label) label.textContent = input.files[0].name;
});

// Category button toggle (radio button style)
document.addEventListener('click', function (e) {
    const btn = e.target.closest('.cat-btn');
    if (!btn) return;
    const group = btn.closest('.cat-group');
    if (!group) return;
    group.querySelectorAll('.cat-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');

    const input = group.querySelector('input[type=hidden]');
    if (input) input.value = btn.dataset.value || btn.textContent.trim();
});

// Auto-dismiss alerts after 5 seconds
document.querySelectorAll('.alert-dismissible').forEach(function (el) {
    setTimeout(function () {
        el.style.opacity = '0';
        el.style.transition = 'opacity 0.4s';
        setTimeout(function () { el.remove(); }, 400);
    }, 5000);
});
