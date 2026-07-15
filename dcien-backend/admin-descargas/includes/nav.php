<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!-- Sidebar Navegación -->
<nav class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <h2 class="sidebar-logo">DCIEN</h2>
    </div>
    <ul class="sidebar-nav">
        <li>
            <a href="/admin-descargas/index.php" class="<?php echo $current_page == 'index.php' ? 'active' : ''; ?>">
                <span class="icon">📊</span> Dashboard
            </a>
        </li>
        <li>
            <a href="/admin-descargas/modules/usuarios.php" class="<?php echo $current_page == 'usuarios.php' ? 'active' : ''; ?>">
                <span class="icon">👥</span> Atletas
            </a>
        </li>
        <li>
            <a href="/admin-descargas/modules/activacion.php" class="<?php echo $current_page == 'activacion.php' ? 'active' : ''; ?>">
                <span class="icon">🎟️</span> Invitar
            </a>
        </li>
        <li>
            <a href="/admin-descargas/modules/descuentos.php" class="<?php echo $current_page == 'descuentos.php' ? 'active' : ''; ?>">
                <span class="icon">🏅</span> Validaciones
            </a>
        </li>
        <li>
            <a href="/admin-descargas/modules/bonos.php" class="<?php echo $current_page == 'bonos.php' ? 'active' : ''; ?>">
                <span class="icon">🎟️</span> Bonos QR
            </a>
        </li>
        <li>
            <a href="/admin-descargas/modules/auditor.php" class="<?php echo $current_page == 'auditor.php' ? 'active' : ''; ?>">
                <span class="icon">👁️</span> Auditor
            </a>
        </li>
        <li>
            <a href="/admin-descargas/modules/series.php" class="<?php echo $current_page == 'series.php' ? 'active' : ''; ?>">
                <span class="icon">📦</span> Series
            </a>
        </li>
        <li>
            <a href="/admin-descargas/modules/prioridad.php" class="<?php echo $current_page == 'prioridad.php' ? 'active' : ''; ?>">
                <span class="icon">🔐</span> Acceso VIP
            </a>
        </li>
        <li>
            <a href="/admin-descargas/modules/marketing.php" class="<?php echo $current_page == 'marketing.php' ? 'active' : ''; ?>">
                <span class="icon">🎁</span> Marketing
            </a>
        </li>
        <li>
            <a href="/admin-descargas/modules/comentarios.php" class="<?php echo $current_page == 'comentarios.php' ? 'active' : ''; ?>">
                <span class="icon">💬</span> Comentarios
            </a>
        </li>
        <li>
            <a href="/admin-descargas/modules/logs.php" class="<?php echo $current_page == 'logs.php' ? 'active' : ''; ?>">
                <span class="icon">📋</span> Logs Sistema
            </a>
        </li>
        <li>
            <a href="/admin-descargas/ordenes/index.php" class="<?php echo strpos($_SERVER['PHP_SELF'], '/ordenes/') !== false ? 'active' : ''; ?>">
                <span class="icon">📄</span> Órdenes HTML
            </a>
        </li>
        <li>
            <a href="/admin-descargas/perfil-esfuerzo.html" class="<?php echo $current_page == 'perfil-esfuerzo.html' ? 'active' : ''; ?>">
                <span class="icon">🎬</span> IG Animaciones
            </a>
        </li>
    </ul>
</nav>

<!-- Overlay móvil -->
<div class="overlay" id="sidebar-overlay" onclick="toggleMobileNav()"></div>

<!-- Botón Flotante Móvil (Letra D de DCIEN) -->
<button class="mobile-nav-toggle" id="mobile-nav-toggle" onclick="toggleMobileNav()">
    D
</button>

<script>
// Bloquea el scroll de la página de fondo mientras el menú móvil está abierto,
// para que un gesto de scroll dentro del sidebar no mueva también la ventana trasera.
let dcienNavScrollY = 0;

function toggleMobileNav() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    const isOpening = !sidebar.classList.contains('open');

    sidebar.classList.toggle('open');
    overlay.classList.toggle('open');

    if (isOpening) {
        dcienNavScrollY = window.scrollY;
        document.body.classList.add('nav-locked');
        document.body.style.top = `-${dcienNavScrollY}px`;
    } else {
        document.body.classList.remove('nav-locked');
        document.body.style.top = '';
        window.scrollTo(0, dcienNavScrollY);
    }
}
</script>
