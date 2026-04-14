    </main>

    <footer class="site-footer">
        <div class="footer-inner container">
            <div class="footer-brand">
                <span class="footer-logo" aria-hidden="true"></span>
                <div>
                    <strong>Futbol Data</strong>
                    <p class="footer-tagline">Ligas, equipos y datos con una interfaz clara.</p>
                </div>
            </div>
            <nav class="footer-links" aria-label="Pie de página">
                <a href="<?= htmlspecialchars(fc_url('/index.php')) ?>">Inicio</a>
                <a href="<?= htmlspecialchars(fc_url('/ligas.php')) ?>">Ligas</a>
                <a href="<?= htmlspecialchars(fc_url('/buscar.php')) ?>">Buscar</a>
                <a href="<?= htmlspecialchars(fc_url('/admin/index.php')) ?>">Administración</a>
            </nav>
            <p class="footer-copy">&copy; <?= date('Y') ?> Futbol Data</p>
        </div>
    </footer>

    <script src="<?= htmlspecialchars(fc_url('/js/site.js')) ?>" defer></script>
</body>

</html>
