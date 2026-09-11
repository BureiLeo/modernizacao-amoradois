<?php
require_once __DIR__.'/db.php';
require_once __DIR__.'/auth.php';
auth_require();
$pdo = db();

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
  header('Location: clientes.php');
  exit;
}

$err = null;

// Buscar cliente
$st = $pdo->prepare("SELECT * FROM clientes WHERE id = ?");
$st->execute([$id]);
$cliente = $st->fetch();

if (!$cliente) {
  header('Location: clientes.php?msg=Cliente não encontrado');
  exit;
}

if ($_SERVER['REQUEST_METHOD']==='POST') {
  try {
    $nome = trim($_POST['nome']);
    $telefone = trim($_POST['telefone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $cpf = trim($_POST['cpf'] ?? '');
    $rg = trim($_POST['rg'] ?? '');
    $cnpj = trim($_POST['cnpj'] ?? '');
    $razao_social = trim($_POST['razao_social'] ?? '');
    $endereco = trim($_POST['endereco'] ?? '');
    $obs = trim($_POST['obs'] ?? '');
    
    if (!$nome) throw new Exception('Nome é obrigatório');
    
    $st = $pdo->prepare("
      UPDATE clientes 
      SET nome = ?, telefone = ?, email = ?, cpf = ?, rg = ?, cnpj = ?, razao_social = ?, endereco = ?, obs = ?
      WHERE id = ?
    ");
    $st->execute([$nome, $telefone, $email, $cpf, $rg, $cnpj, $razao_social, $endereco, $obs, $id]);
    
    header('Location: clientes.php?msg=Cliente atualizado com sucesso!');
    exit;
  } catch (Throwable $e) { 
    $err = $e->getMessage(); 
  }
}

$title = 'Editar Cliente';
include __DIR__.'/layout_start.php';
?>

<?php if($err): ?><div class="alert alert-danger"><?=htmlspecialchars($err)?></div><?php endif; ?>

<form class="card" method="post">
  <h3>Editar Cliente #<?=$cliente['codigo']?></h3>
  
  <div class="mb-3">
    <label class="form-label">Código</label>
    <input type="text" class="form-control" value="#<?=$cliente['codigo']?>" disabled>
    <small class="text-muted">Use este número para buscar o cliente rapidamente nas vendas</small>
  </div>
  
  <div class="mb-3">
    <label class="form-label">Nome / Nome Fantasia *</label>
    <input type="text" name="nome" class="form-control" value="<?=htmlspecialchars($cliente['nome'])?>" required>
  </div>
  
  <div class="row">
    <div class="col-md-6 mb-3">
      <label class="form-label">Telefone</label>
      <input type="text" name="telefone" class="form-control" value="<?=htmlspecialchars($cliente['telefone'] ?? '')?>" placeholder="(00) 00000-0000">
    </div>
    
    <div class="col-md-6 mb-3">
      <label class="form-label">Email</label>
      <input type="email" name="email" class="form-control" value="<?=htmlspecialchars($cliente['email'] ?? '')?>">
    </div>
  </div>
  
  <hr>
  <h5 class="mb-3">Pessoa Física</h5>
  
  <div class="row">
    <div class="col-md-6 mb-3">
      <label class="form-label">CPF</label>
      <input type="text" name="cpf" class="form-control" value="<?=htmlspecialchars($cliente['cpf'] ?? '')?>" placeholder="000.000.000-00">
    </div>
    
    <div class="col-md-6 mb-3">
      <label class="form-label">RG</label>
      <input type="text" name="rg" class="form-control" value="<?=htmlspecialchars($cliente['rg'] ?? '')?>">
    </div>
  </div>
  
  <hr>
  <h5 class="mb-3">Pessoa Jurídica</h5>
  
  <div class="row">
    <div class="col-md-6 mb-3">
      <label class="form-label">CNPJ</label>
      <input type="text" name="cnpj" class="form-control" value="<?=htmlspecialchars($cliente['cnpj'] ?? '')?>" placeholder="00.000.000/0000-00">
    </div>
    
    <div class="col-md-6 mb-3">
      <label class="form-label">Razão Social</label>
      <input type="text" name="razao_social" class="form-control" value="<?=htmlspecialchars($cliente['razao_social'] ?? '')?>">
    </div>
  </div>
  
  <hr>
  
  <div class="mb-3">
    <label class="form-label">Endereço</label>
    <textarea name="endereco" class="form-control" rows="2"><?=htmlspecialchars($cliente['endereco'] ?? '')?></textarea>
  </div>
  
  <div class="mb-3">
    <label class="form-label">Observações</label>
    <textarea name="obs" class="form-control" rows="2"><?=htmlspecialchars($cliente['obs'] ?? '')?></textarea>
  </div>
  
  <div class="mb-3">
    <small class="text-muted">Cadastrado em: <?=date('d/m/Y H:i', strtotime($cliente['criado_em']))?></small>
  </div>
  
  <div class="d-flex gap-2">
    <button type="submit" class="btn btn-primary">✅ Salvar Alterações</button>
    <a href="clientes.php" class="btn btn-secondary">Cancelar</a>
  </div>
</form>

<?php include __DIR__.'/layout_end.php'; ?>
