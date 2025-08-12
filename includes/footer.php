</main>

<footer class="container mt-5 text-center text-muted">
    <p>&copy; <?php echo date('Y'); ?> ClientesAdmin</p>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
// Función para confirmar borrado
function confirmarBorrado(url) {
    if (confirm("¿Estás seguro de que deseas eliminar este registro? Esta acción no se puede deshacer.")) {
        window.location.href = url;
    }
}
</script>
</body>
</html>