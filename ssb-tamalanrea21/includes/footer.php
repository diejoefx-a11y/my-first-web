<?php
$current_dir = basename(dirname($_SERVER['PHP_SELF']));
$baseUrl = ($current_dir === 'atlet' || $current_dir === 'evaluasi' || $current_dir === 'iuran' || $current_dir === 'turnamen') ? '../' : './';
?>
        </main>
    </div>

    <script src="<?= $baseUrl ?>assets/js/main.js"></script>
</body>
</html>
