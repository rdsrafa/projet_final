<?php
// include/footer.php - Footer global

?>

<!-- Footer (si nécessaire) -->
<script>
// Code JavaScript global
console.log('Adoo Sneakers ERP v1.0 - Loaded');

// Animation pour les cartes au scroll
const observerOptions = {
    threshold: 0.1,
    rootMargin: '0px 0px -50px 0px'
};

const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            entry.target.classList.add('fade-in');
            observer.unobserve(entry.target);
        }
    });
}, observerOptions);

document.addEventListener('DOMContentLoaded', () => {
    // Observer les éléments
    document.querySelectorAll('.card, .table-container, .stock-item').forEach(el => {
        observer.observe(el);
    });
    
    // Gestion des messages flash (auto-close après 5s)
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-20px)';
            setTimeout(() => alert.remove(), 300);
        }, 5000);
    });
});
</script>