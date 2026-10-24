<?php
// Recuperamos la variable 'pending_matches' que nos preparó el ValidationController.
$a_pending_matches = $this->getVar('pending_matches');
?>

<div class="container-fluid">
    <h2>Panel de Validación de Identificadores Externos</h2>
    <p>Revisa las correspondencias automáticas con riesgo de ambigüedad antes de asociarlas al catálogo.</p>

    <!-- Estructura básica de una tabla en Bootstrap (HTML) -->
    <table class="table table-striped table-hover">
        <thead>
            <tr>
                <th>ID Registro</th>
                <th>Nombre en Catálogo</th>
                <th>Coincidencia Externa</th>
                <th>Fuente / URI</th>
                <th>Puntaje de Confianza</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <!-- Verificamos si la lista está vacía -->
            <?php if (empty($a_pending_matches)): ?>
                <tr>
                    <td colspan="6" class="text-center">No hay correspondencias pendientes de revisión.</td>
                </tr>
            <?php else: ?>
                <!-- Iteramos (bucle for-each) sobre cada elemento de la lista entregada por Rust -->
                <?php foreach ($a_pending_matches as $a_match): ?>
                    <tr>
                        <!-- Muestra el ID del objeto local en Providence -->
                        <td><?= htmlspecialchars($a_match['record_id']) ?></td>
                        <!-- Muestra el nombre como está escrito en nuestro catálogo -->
                        <td><?= htmlspecialchars($a_match['local_name']) ?></td>
                        <!-- Muestra el nombre hallado en la base externa (ej: Wikidata) -->
                        <td><?= htmlspecialchars($a_match['external_name']) ?></td>
                        <!-- Muestra un enlace cliqueable con la URI oficial externa -->
                        <td><a href="<?= htmlspecialchars($a_match['uri']) ?>" target="_blank"><?= htmlspecialchars($a_match['uri']) ?></a></td>
                        <td>
                            <!-- Si el porcentaje de confianza es mayor a 85%, pinta la etiqueta amarilla; si no, roja -->
                            <span class="badge <?= $a_match['score'] > 0.85 ? 'bg-warning' : 'bg-danger' ?>">
                                <?= number_format($a_match['score'] * 100, 1) ?>%
                            </span>
                        </td>
                        <td>
                            <!-- Botón "Aprobar" que ejecuta la función JavaScript pasándole el ID y la URI -->
                            <button class="btn btn-success btn-sm" onclick="approveMatch('<?= $a_match['record_id'] ?>', '<?= $a_match['uri'] ?>')">Aprobar</button>
                            <button class="btn btn-danger btn-sm" onclick="rejectMatch('<?= $a_match['record_id'] ?>')">Rechazar</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Código JavaScript para interactuar con la página sin necesidad de recargarla completamente -->
<script>
function approveMatch(recordId, uri) {
    // Hace una petición POST en segundo plano (vía AJAX/fetch) a nuestro ValidationController -> approveAction.
    fetch('<?= caNavUrl($this->request, "ExternalServicesPlugin", "Validation", "approve") ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `record_id=${recordId}&uri=${encodeURIComponent(uri)}`
    })
    .then(res => res.json())
    .then(data => location.reload()); // Cuando el servidor responde OK, recarga la pantalla para actualizar la lista.
}
</script>