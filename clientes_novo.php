<?php
require_once __DIR__.'/db.php';
require_once __DIR__.'/auth.php';
auth_require();
$pdo = db();

$err = null;

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
    
    // Pegar próximo código
    $maxCodigo = $pdo->query("SELECT IFNULL(MAX(codigo), 0) + 1 AS proximo FROM clientes")->fetch();
    $codigo = (int)$maxCodigo['proximo'];
    
    $st = $pdo->prepare("
      INSERT INTO clientes (codigo, nome, telefone, email, cpf, rg, cnpj, razao_social, endereco, obs)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $st->execute([$codigo, $nome, $telefone, $email, $cpf, $rg, $cnpj, $razao_social, $endereco, $obs]);
    
    header('Location: clientes.php?msg=Cliente cadastrado com sucesso!');
    exit;
  } catch (Throwable $e) { 
    $err = $e->getMessage(); 
  }
}

$title = 'Novo Cliente';
include __DIR__.'/layout_start.php';
?>

<?php if($err): ?><div class="alert alert-danger"><?=htmlspecialchars($err)?></div><?php endif; ?>

<form class="card" method="post">
  <h3>Cadastrar Novo Cliente</h3>
  
  <div class="mb-3">
    <label class="form-label">Nome / Nome Fantasia *</label>
    <input type="text" name="nome" class="form-control" required>
  </div>
  
  <div class="row">
    <div class="col-md-6 mb-3">
      <label class="form-label">Telefone</label>
      <input type="text" name="telefone" class="form-control" placeholder="(00) 00000-0000">
    </div>
    
    <div class="col-md-6 mb-3">
      <label class="form-label">Email</label>
      <input type="email" name="email" class="form-control">
    </div>
  </div>
  
  <hr>
  <h5 class="mb-3">Pessoa Física</h5>
  
  <div class="row">
    <div class="col-md-6 mb-3">
      <label class="form-label">CPF</label>
      <input type="text" name="cpf" class="form-control" placeholder="000.000.000-00">
    </div>
    
    <div class="col-md-6 mb-3">
      <label class="form-label">RG</label>
      <input type="text" name="rg" class="form-control">
    </div>
  </div>
  
  <hr>
  <h5 class="mb-3">Pessoa Jurídica</h5>
  
  <div class="row">
    <div class="col-md-6 mb-3">
      <label class="form-label">CNPJ</label>
      <input type="text" name="cnpj" class="form-control" placeholder="00.000.000/0000-00">
    </div>
    
    <div class="col-md-6 mb-3">
      <label class="form-label">Razão Social</label>
      <input type="text" name="razao_social" class="form-control">
    </div>
  </div>
  
  <hr>
  
  <div class="mb-3">
    <label class="form-label">Endereço</label>
    <textarea name="endereco" class="form-control" rows="2" placeholder="Rua, número, bairro, cidade..."></textarea>
  </div>
  
  <div class="mb-3">
    <label class="form-label">Observações</label>
    <textarea name="obs" class="form-control" rows="2"></textarea>
  </div>
  
  <div class="d-flex gap-2">
    <button type="submit" class="btn btn-primary">✅ Salvar Cliente</button>
    <a href="clientes.php" class="btn btn-secondary">Cancelar</a>
  </div>
</form>

<?php include __DIR__.'/layout_end.php'; ?>
