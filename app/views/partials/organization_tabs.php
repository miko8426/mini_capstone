<?php
$orgTabs = [
    ['href' => '/campuses', 'label' => 'Campuses', 'match' => '/campuses'],
    ['href' => '/offices', 'label' => 'Offices', 'match' => '/offices'],
];
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
?>
<ul class="nav nav-pills mb-4 gap-2">
    <?php foreach ($orgTabs as $tab): ?>
        <li class="nav-item">
            <a class="nav-link <?= $currentPath === $tab['match'] ? 'active bg-success text-white' : 'text-success' ?>" href="<?= $tab['href'] ?>"><?= $tab['label'] ?></a>
        </li>
    <?php endforeach; ?>
</ul>
