<footer class="footer">
    <p>&copy; <?php echo date("Y"); ?> GRUPO AZUL Flota Taxis | Desarollado por EOVSYSTEMS SERVICES & TECHNOLOGY S.L | Todos los derechos reservados</p>
</footer>
<!-- layout/footer.php -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const marquee = document.querySelector('.marquee');
    if (marquee) {
        marquee.addEventListener('mouseenter', function() {
            this.style.animationPlayState = 'paused';
        });
        
        marquee.addEventListener('mouseleave', function() {
            this.style.animationPlayState = 'running';
        });
    }
});
</script>
</body>
</html>
