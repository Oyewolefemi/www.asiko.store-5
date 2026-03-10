<footer class="p-4 mt-8 bg-white border-t border-gray-200 text-center">
            <p class="text-sm text-gray-500">&copy; <?= date('Y') ?> <?= htmlspecialchars(EnvLoader::get('STORE_NAME', 'ASIKO')) ?></p>
        </footer>
    </div> <!-- This closes the .page-wrapper from header.php -->

    <!-- JavaScript for Mobile Menu Toggle -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const menuButton = document.getElementById('mobile-menu-button');
            const mobileMenuPanel = document.getElementById('mobile-menu');
            const hamburgerIcon = document.getElementById('hamburger-icon');
            const closeIcon = document.getElementById('close-icon');

            if (menuButton && mobileMenuPanel && hamburgerIcon && closeIcon) {
                menuButton.addEventListener('click', function() {
                    mobileMenuPanel.classList.toggle('hidden');
                    hamburgerIcon.classList.toggle('hidden');
                    closeIcon.classList.toggle('hidden');
                });
            }
        });
    </script>

</body>
</html>