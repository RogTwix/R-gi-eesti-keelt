document.addEventListener("DOMContentLoaded", function() {
    const form = document.getElementById("contactForm");
    
    if(form) {
        form.style.opacity = 0;
        form.style.transition = "opacity 1s ease-in-out";
        
        setTimeout(() => {
            form.style.opacity = 1;
        }, 100);

        form.addEventListener("submit", function(event) {
            const btn = form.querySelector("button[type='submit']");
            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Загрузка...';
            btn.disabled = true;
        });
    }
});