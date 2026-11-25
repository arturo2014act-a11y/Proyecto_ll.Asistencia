<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/validators.php';
require_once __DIR__ . '/../includes/layout.php';

requireRole('profesor');
$user = currentUser();
$db = getDB();

$action = $_GET['action'] ?? '';
$editId = isset($_GET['edit_id']) ? (int)$_GET['edit_id'] : 0;
$errors = [];
$generalError = '';
$successMsg = '';

$formName = '';
$formGroup = '';
$formDesc = '';

if ($editId > 0) {
    $stmt = $db->prepare('SELECT * FROM courses WHERE id = ? AND professor_id = ?');
    $stmt->execute([$editId, $user['id']]);
    $editCourse = $stmt->fetch();
    if (!$editCourse) {
        $generalError = 'No tienes permiso para realizar esta acción';
        $editId = 0;
    } else {
        $formName = $editCourse['name'];
        $formGroup = $editCourse['group_name'];
        $formDesc = (string)($editCourse['description'] ?? '');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postId = (int)($_POST['course_id'] ?? 0);
    $formName = trim((string)($_POST['name'] ?? ''));
    $formGroup = trim((string)($_POST['group_name'] ?? ''));
    $formDesc = trim((string)($_POST['description'] ?? ''));

    $errors = validateCourseData($formName, $formGroup, $formDesc);

    if (empty($errors)) {
        // Verificar duplicado (ignorar mayúsculas y espacios exteriores)
        $dupStmt = $db->prepare('SELECT id FROM courses WHERE professor_id = ? AND LOWER(TRIM(name)) = LOWER(?) AND LOWER(TRIM(group_name)) = LOWER(?) AND id != ?');
        $dupStmt->execute([$user['id'], $formName, $formGroup, $postId]);
        if ($dupStmt->fetch()) {
            $generalError = 'Ya existe un curso con ese nombre y grupo';
        } else {
            if ($postId > 0) {
                $upd = $db->prepare('UPDATE courses SET name = ?, group_name = ?, description = ? WHERE id = ? AND professor_id = ?');
                $upd->execute([$formName, $formGroup, $formDesc !== '' ? $formDesc : null, $postId, $user['id']]);
                $successMsg = 'Curso guardado';
                $editId = 0;
                $action = '';
            } else {
                $ins = $db->prepare('INSERT INTO courses (professor_id, name, group_name, description) VALUES (?, ?, ?, ?)');
                $ins->execute([$user['id'], $formName, $formGroup, $formDesc !== '' ? $formDesc : null]);
                $successMsg = 'Curso guardado';
                $action = '';
                $formName = $formGroup = $formDesc = '';
            }
        }
    } else {
        if ($postId > 0) {
            $editId = $postId;
        } else {
            $action = 'new';
        }
    }
}

$stmt = $db->prepare('SELECT * FROM courses WHERE professor_id = ? ORDER BY created_at DESC');
$stmt->execute([$user['id']]);
$courses = $stmt->fetchAll();

renderHeader('Mis cursos', 'cursos');
?>
<h1>Mis cursos</h1>

<?php if ($successMsg !== ''): ?>
  <div class="alert alert-success"><?= e($successMsg) ?></div>
<?php endif; ?>
<?php if ($generalError !== ''): ?>
  <div class="alert alert-error"><?= e($generalError) ?></div>
<?php endif; ?>

<?php if ($action === 'new' || $editId > 0): ?>
  <div class="card">
    <h2><?= $editId > 0 ? 'Editar curso' : 'Crear curso' ?></h2>
    <form method="POST" action="/sgap/profesor/courses.php" novalidate>
      <input type="hidden" name="course_id" value="<?= $editId ?>">
      <div class="form-group">
        <label for="name">Nombre del curso <span class="required-mark">*</span></label>
        <input type="text" id="name" name="name" value="<?= e($formName) ?>" maxlength="80" required>
        <?php if (isset($errors['name'])): ?>
          <div class="field-error"><?= e($errors['name']) ?></div>
        <?php endif; ?>
      </div>
      <div class="form-group">
        <label for="group_name">Grupo <span class="required-mark">*</span></label>
        <input type="text" id="group_name" name="group_name" value="<?= e($formGroup) ?>" maxlength="10" required>
        <?php if (isset($errors['group_name'])): ?>
          <div class="field-error"><?= e($errors['group_name']) ?></div>
        <?php endif; ?>
      </div>
      <div class="form-group">
        <label for="description">Descripción (opcional)</label>
        <textarea id="description" name="description" maxlength="200"><?= e($formDesc) ?></textarea>
        <?php if (isset($errors['description'])): ?>
          <div class="field-error"><?= e($errors['description']) ?></div>
        <?php endif; ?>
      </div>
      <div style="display:flex; gap:10px; flex-wrap:wrap;">
        <button type="submit" class="btn btn-primary">Guardar</button>
        <a href="/sgap/profesor/courses.php" class="btn btn-secondary">Cancelar</a>
      </div>
    </form>
  </div>
<?php else: ?>
  <div style="margin-bottom:16px;">
    <a href="/sgap/profesor/courses.php?action=new" class="btn btn-primary">Crear curso</a>
  </div>
<?php endif; ?>

<div class="card">
  <h2>Listado de cursos</h2>
  <?php if (empty($courses)): ?>
    <div class="alert alert-info">Sin registros.</div>
  <?php else: ?>
    <div class="table-responsive">
      <table>
        <thead>
          <tr>
            <th>Nombre</th>
            <th>Grupo</th>
            <th>Descripción</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($courses as $c): ?>
            <tr>
              <td><?= e($c['name']) ?></td>
              <td><?= e($c['group_name']) ?></td>
              <td><?= e((string)($c['description'] ?? '')) ?></td>
              <td style="display:flex; gap:8px; flex-wrap:wrap;">
                <a href="/sgap/profesor/enrollments.php?course_id=<?= (int)$c['id'] ?>" class="btn btn-primary btn-sm">Ver</a>
                <a href="/sgap/profesor/courses.php?edit_id=<?= (int)$c['id'] ?>" class="btn btn-secondary btn-sm">Editar</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
<?php renderFooter(); ?>
