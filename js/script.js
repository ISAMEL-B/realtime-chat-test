document.getElementById('sendForm').addEventListener('submit', function(e) {
    e.preventDefault();

    const form = e.target;
    const formData = new FormData(form);

    fetch('send_message.php', {
        method: 'POST',
        body: formData
    }).then(res => res.text()).then(data => {
        // Reload chat for now
        location.reload();
    });
});
