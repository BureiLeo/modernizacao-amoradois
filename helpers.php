<?php
// helpers.php — operações de estoque, vendas, perdas e caixa
require_once __DIR__.'/db.php';

/* ============================================================
   COMPRA: atualiza custo médio ponderado e estoque
   ============================================================ */
function registrar_compra(
  int $materialId,
  int $qtdEmbalagem,
  int $fator,
  float $custoTotal,
  ?float $frete = null,
  ?float $desconto = null
): int {
  $pdo = db();
  $pdo->beginTransaction();
  try {
    $stLock = $pdo->prepare("SELECT estoque, custo_medio FROM materiais WHERE id = ? FOR UPDATE");
    $stLock->execute([$materialId]);
    $m = $stLock->fetch();
    if (!$m) throw new Exception('Material não encontrado');

    $qtd_base = $qtdEmbalagem * $fator;
    if ($qtd_base <= 0) throw new Exception('Quantidade inválida');

  $total_com_frete_e_desconto = $custoTotal + (float)($frete ?? 0) - (float)($desconto ?? 0);
  $custo_unit = $total_com_frete_e_desconto / $qtd_base;

    $ins = $pdo->prepare("
      INSERT INTO compras (data, material_id, qtd_embalagem, qtd_base, custo_total, custo_unit, frete)
      VALUES (CURDATE(), ?, ?, ?, ?, ?, ?)
    ");
    $ins->execute([$materialId, $qtdEmbalagem, $qtd_base, $custoTotal, $custo_unit, $frete]);
    $compraId = (int)$pdo->lastInsertId();

    $estoque_ant = (int)$m['estoque'];
  $cm_ant      = (float)$m['custo_medio'];
    $novo_estoque = $estoque_ant + $qtd_base;
  // custo médio ponderado considerando que custo_unit já inclui frete e desconto
  $novo_cm = ($estoque_ant * $cm_ant + $qtd_base * $custo_unit) / max(1, $novo_estoque);

    $upd = $pdo->prepare("UPDATE materiais SET estoque = ?, custo_medio = ? WHERE id = ?");
    $upd->execute([$novo_estoque, $novo_cm, $materialId]);

    $pdo->commit();
    return $compraId;
  } catch (Throwable $e) {
    $pdo->rollBack();
    throw $e;
  }
}

/* ============================================================
   VENDA: usa BOM do momento (snapshot em venda_consumos)
   ============================================================ */
function registrar_venda(
  DateTime $data,
  int $produtoId,
  int $qtd,
  float $precoUnit,
  ?string $canal = null,
  ?string $obs = null,
  ?string $forma_pagamento = null,
  int $pago = 0
): int {
  $pdo = db();
  $pdo->beginTransaction();
  try {
    $bom = $pdo->prepare("
      SELECT pb.material_id, pb.qtd_por_unidade, m.estoque, m.custo_medio
      FROM produto_bom pb
      JOIN materiais m ON m.id = pb.material_id
      WHERE pb.produto_id = ?
    ");
    $bom->execute([$produtoId]);
    $rows = $bom->fetchAll();
    if (!$rows) throw new Exception('Produto sem BOM definida.');

    $custo_unit = 0.0;
    $consumos = [];
    foreach ($rows as $r) {
      $nec = (int)ceil($r['qtd_por_unidade'] * $qtd);
      if ((int)$r['estoque'] < $nec) throw new Exception('Estoque insuficiente do material ID '.$r['material_id']);
      $custo_unit += (float)$r['qtd_por_unidade'] * (float)$r['custo_medio'];
      $consumos[] = ['mat'=>(int)$r['material_id'], 'qtd'=>$nec, 'cm'=>(float)$r['custo_medio']];
    }
    $lucro_unit = $precoUnit - $custo_unit;

    // Cabeçalho
    $pdo->prepare("
      INSERT INTO vendas (data, canal, obs, forma_pagamento, pago)
      VALUES (?, ?, ?, ?, ?)
    ")->execute([$data->format('Y-m-d H:i:s'), $canal, $obs, $forma_pagamento, $pago]);
    $vendaId = (int)$pdo->lastInsertId();

    // Item
    $pdo->prepare("
      INSERT INTO venda_itens (venda_id, produto_id, qtd, preco_unit, custo_unit_calculado, lucro_unit)
      VALUES (?, ?, ?, ?, ?, ?)
    ")->execute([$vendaId, $produtoId, $qtd, $precoUnit, $custo_unit, $lucro_unit]);

    // Snapshot + baixa estoque
    $insSnap = $pdo->prepare("
      INSERT INTO venda_consumos (venda_id, material_id, qtd_consumida, custo_unit_no_momento)
      VALUES (?, ?, ?, ?)
    ");
    foreach ($consumos as $c) {
      $pdo->prepare("UPDATE materiais SET estoque = estoque - ? WHERE id = ?")->execute([$c['qtd'], $c['mat']]);
      $insSnap->execute([$vendaId, $c['mat'], $c['qtd'], $c['cm']]);
    }

    // Caixa: se pago na criação
    if ($pago) {
      $st = $pdo->prepare("SELECT IFNULL(SUM(qtd*preco_unit),0) t FROM venda_itens WHERE venda_id = ?");
      $st->execute([$vendaId]);
      $t = (float)($st->fetch()['t'] ?? 0);
      caixa_lancar($data, 'VENDA', 'vendas', $vendaId, $t, 'Venda #'.$vendaId);
    }

    $pdo->commit();
    return $vendaId;
  } catch (Throwable $e) {
    $pdo->rollBack();
    throw $e;
  }
}

/* ============================================================
   VENDA + PERDA (mesma operação, usando BOM)
   ============================================================ */
function registrar_venda_com_perda(
  DateTime $data,
  int $produtoId,
  int $qtdVenda,
  int $qtdPerda,                     // 0 = sem perda
  float $precoUnit,
  ?string $canal = null,
  ?string $obsVenda = null,
  ?string $forma_pagamento = null,
  int $pago = 0,
  ?string $motivoPerda = null,
  ?string $obsPerda = null
): int {
  if ($qtdPerda <= 0) {
    return registrar_venda($data, $produtoId, $qtdVenda, $precoUnit, $canal, $obsVenda, $forma_pagamento, $pago);
  }

  $pdo = db();
  $pdo->beginTransaction();
  try {
    $bom = $pdo->prepare("
      SELECT pb.material_id, pb.qtd_por_unidade, m.estoque, m.custo_medio
      FROM produto_bom pb
      JOIN materiais m ON m.id = pb.material_id
      WHERE pb.produto_id = ?
    ");
    $bom->execute([$produtoId]);
    $rows = $bom->fetchAll();
    if (!$rows) throw new Exception('Produto sem BOM definida.');

    $custo_unit = 0.0;
    $snapVenda = [];
    $snapPerda = [];
    $custo_total_perda = 0.0;

    foreach ($rows as $r) {
      $qpu = (float)$r['qtd_por_unidade'];
      $cm  = (float)$r['custo_medio'];
      $estoq = (int)$r['estoque'];

      $necVenda = (int)ceil($qpu * $qtdVenda);
      $necPerda = (int)ceil($qpu * $qtdPerda);
      $necTotal = $necVenda + $necPerda;

      if ($estoq < $necTotal) throw new Exception('Estoque insuficiente do material ID '.$r['material_id'].' para venda+perda');

      $custo_unit += $qpu * $cm;
      $custo_total_perda += $necPerda * $cm;

      if ($necVenda > 0) $snapVenda[] = ['mat'=>(int)$r['material_id'], 'qtd'=>$necVenda, 'cm'=>$cm];
      if ($necPerda > 0) $snapPerda[] = ['mat'=>(int)$r['material_id'], 'qtd'=>$necPerda, 'cm'=>$cm];
    }
    $lucro_unit = $precoUnit - $custo_unit;

    // Cabeçalho + item
    $pdo->prepare("
      INSERT INTO vendas (data, canal, obs, forma_pagamento, pago)
      VALUES (?, ?, ?, ?, ?)
    ")->execute([$data->format('Y-m-d H:i:s'), $canal, $obsVenda, $forma_pagamento, $pago]);
    $vendaId = (int)$pdo->lastInsertId();

    $pdo->prepare("
      INSERT INTO venda_itens (venda_id, produto_id, qtd, preco_unit, custo_unit_calculado, lucro_unit)
      VALUES (?, ?, ?, ?, ?, ?)
    ")->execute([$vendaId, $produtoId, $qtdVenda, $precoUnit, $custo_unit, $lucro_unit]);

    // Snapshot da venda
    $insSnapV = $pdo->prepare("
      INSERT INTO venda_consumos (venda_id, material_id, qtd_consumida, custo_unit_no_momento)
      VALUES (?, ?, ?, ?)
    ");
    foreach ($snapVenda as $sv) {
      $insSnapV->execute([$vendaId, $sv['mat'], $sv['qtd'], $sv['cm']]);
    }

    // Perda
    $pdo->prepare("
      INSERT INTO perdas (data, tipo, produto_id, material_id, qtd, motivo, obs, custo_total)
      VALUES (?, 'PRODUTO', ?, NULL, ?, ?, ?, ?)
    ")->execute([$data->format('Y-m-d H:i:s'), $produtoId, $qtdPerda, $motivoPerda, $obsPerda, $custo_total_perda]);
    $perdaId = (int)$pdo->lastInsertId();

    // Snapshot da perda
    $insSnapP = $pdo->prepare("
      INSERT INTO perda_consumos (perda_id, material_id, qtd_consumida, custo_unit_no_momento)
      VALUES (?, ?, ?, ?)
    ");
    foreach ($snapPerda as $sp) {
      $insSnapP->execute([$perdaId, $sp['mat'], $sp['qtd'], $sp['cm']]);
    }

    // Baixa estoque (venda + perda)
    $baixar = [];
    foreach ($snapVenda as $sv){ $baixar[$sv['mat']] = ($baixar[$sv['mat']] ?? 0) + $sv['qtd']; }
    foreach ($snapPerda as $sp){ $baixar[$sp['mat']] = ($baixar[$sp['mat']] ?? 0) + $sp['qtd']; }
    foreach ($baixar as $mid => $qt) {
      if ($qt > 0) $pdo->prepare("UPDATE materiais SET estoque = estoque - ? WHERE id = ?")->execute([$qt, $mid]);
    }

    // Caixa: se pago na criação
    if ($pago) {
      $st = $pdo->prepare("SELECT IFNULL(SUM(qtd*preco_unit),0) t FROM venda_itens WHERE venda_id = ?");
      $st->execute([$vendaId]);
      $t = (float)($st->fetch()['t'] ?? 0);
      caixa_lancar($data, 'VENDA', 'vendas', $vendaId, $t, 'Venda #'.$vendaId);
    }

    $pdo->commit();
    return $vendaId;
  } catch (Throwable $e) {
    $pdo->rollBack();
    throw $e;
  }
}

/* ============================================================
   VENDA CUSTOM (consumo por unidade definido no formulário)
   ============================================================ */
function registrar_venda_custom(
  DateTime $data,
  int $produtoId,
  int $qtdVenda,
  float $precoUnit,
  array $consumoPU,                 // [{material_id, qpu}]
  ?string $canal = null,
  ?string $obsVenda = null,
  ?string $forma_pagamento = null,
  int $pago = 0,
  int $qtdPerda = 0,
  ?string $motivoPerda = null,
  ?string $obsPerda = null
): int {
  $pdo = db();
  $pdo->beginTransaction();
  try {
    // Normaliza linhas válidas
    $linhas = [];
    foreach($consumoPU as $r){
      $mid = (int)($r['material_id'] ?? 0);
      $qpu = (float)($r['qpu'] ?? 0);
      if ($mid > 0 && $qpu > 0) $linhas[] = ['material_id'=>$mid, 'qpu'=>$qpu];
    }
    if (!$linhas) throw new Exception('Nenhum material selecionado para esta venda.');

    // Carrega custo/estoque
    $map = [];
    foreach ($linhas as $r) {
      $st = $pdo->prepare("SELECT id, estoque, custo_medio FROM materiais WHERE id = ? FOR UPDATE");
      $st->execute([$r['material_id']]);
      $m = $st->fetch();
      if (!$m) throw new Exception('Material inexistente: '.$r['material_id']);
      $map[$r['material_id']] = ['estoque'=>(int)$m['estoque'], 'cm'=>(float)$m['custo_medio']];
    }

    // Valida estoque e calcula custos
    $custo_unit = 0.0;
    $snapVenda = [];
    $snapPerda = [];
    $custo_total_perda = 0.0;

    foreach ($linhas as $r) {
      $mid = $r['material_id'];
      $qpu = $r['qpu'];
      $cm  = $map[$mid]['cm'];
      $necVenda = (int)ceil($qpu * $qtdVenda);
      $necPerda = (int)ceil($qpu * $qtdPerda);
      $necTotal = $necVenda + $necPerda;

      if ($map[$mid]['estoque'] < $necTotal) {
        throw new Exception('Estoque insuficiente do material ID '.$mid.' (precisa '.$necTotal.')');
      }
      $custo_unit += $qpu * $cm;
      if ($necVenda > 0) $snapVenda[] = ['mat'=>$mid, 'qtd'=>$necVenda, 'cm'=>$cm];
      if ($necPerda > 0){ $snapPerda[] = ['mat'=>$mid, 'qtd'=>$necPerda, 'cm'=>$cm]; $custo_total_perda += $necPerda * $cm; }
    }
    $lucro_unit = $precoUnit - $custo_unit;

    // Cabeçalho + item
    $pdo->prepare("INSERT INTO vendas (data, canal, obs, forma_pagamento, pago) VALUES (?,?,?,?,?)")
        ->execute([$data->format('Y-m-d H:i:s'), $canal, $obsVenda, $forma_pagamento, $pago]);
    $vendaId = (int)$pdo->lastInsertId();

    $pdo->prepare("
      INSERT INTO venda_itens (venda_id, produto_id, qtd, preco_unit, custo_unit_calculado, lucro_unit)
      VALUES (?,?,?,?,?,?)
    ")->execute([$vendaId, $produtoId, $qtdVenda, $precoUnit, $custo_unit, $lucro_unit]);

    // Snapshots
    $insV = $pdo->prepare("INSERT INTO venda_consumos (venda_id, material_id, qtd_consumida, custo_unit_no_momento) VALUES (?,?,?,?)");
    foreach ($snapVenda as $sv) { $insV->execute([$vendaId, $sv['mat'], $sv['qtd'], $sv['cm']]); }

    if ($qtdPerda > 0) {
      $pdo->prepare("INSERT INTO perdas (data, tipo, produto_id, material_id, qtd, motivo, obs, custo_total)
                     VALUES (?,?,?,?,?,?,?,?)")
          ->execute([$data->format('Y-m-d H:i:s'), 'PRODUTO', $produtoId, null, $qtdPerda, $motivoPerda, $obsPerda, $custo_total_perda]);
      $perdaId = (int)$pdo->lastInsertId();
      $insP = $pdo->prepare("INSERT INTO perda_consumos (perda_id, material_id, qtd_consumida, custo_unit_no_momento) VALUES (?,?,?,?)");
      foreach ($snapPerda as $sp) { $insP->execute([$perdaId, $sp['mat'], $sp['qtd'], $sp['cm']]); }
    }

    // Baixa estoque (venda + perda)
    $baixar = [];
    foreach ($snapVenda as $sv){ $baixar[$sv['mat']] = ($baixar[$sv['mat']] ?? 0) + $sv['qtd']; }
    foreach ($snapPerda as $sp){ $baixar[$sp['mat']] = ($baixar[$sp['mat']] ?? 0) + $sp['qtd']; }
    foreach ($baixar as $mid => $qt) {
      if ($qt > 0) $pdo->prepare("UPDATE materiais SET estoque = estoque - ? WHERE id = ?")->execute([$qt, $mid]);
    }

    // Caixa: se pago na criação
    if ($pago) {
      $st = $pdo->prepare("SELECT IFNULL(SUM(qtd*preco_unit),0) t FROM venda_itens WHERE venda_id = ?");
      $st->execute([$vendaId]);
      $t = (float)($st->fetch()['t'] ?? 0);
      caixa_lancar($data, 'VENDA', 'vendas', $vendaId, $t, 'Venda #'.$vendaId);
    }

    $pdo->commit();
    return $vendaId;
  } catch (Throwable $e) {
    $pdo->rollBack();
    throw $e;
  }
}

/* ============================================================
   ATUALIZAR VENDA (estorna snapshot antigo e reaplica)
   - Atualiza também o movimento de caixa conforme "pago"
   ============================================================ */
function atualizar_venda(
  int $vendaId,
  DateTime $novaData,
  int $novoProdutoId,
  int $novaQtd,
  float $novoPrecoUnit,
  ?string $canal = null,
  ?string $obs = null,
  ?string $forma_pagamento = null,
  int $pago = 0
): void {
  $pdo = db();
  $pdo->beginTransaction();
  try {
    // Trava cabeçalho + item
    $row = $pdo->prepare("
      SELECT v.id, v.pago, vi.produto_id, vi.qtd
      FROM vendas v
      JOIN venda_itens vi ON vi.venda_id = v.id
      WHERE v.id = ?
      FOR UPDATE
    ");
    $row->execute([$vendaId]);
    $cur = $row->fetch();
    if (!$cur) throw new Exception('Venda não encontrada');

    // 1) Estorna estoque pelo snapshot antigo
    $snap = $pdo->prepare("SELECT material_id, qtd_consumida FROM venda_consumos WHERE venda_id = ? FOR UPDATE");
    $snap->execute([$vendaId]);
    $snapRows = $snap->fetchAll();

    if ($snapRows) {
      foreach ($snapRows as $s) {
        $pdo->prepare("UPDATE materiais SET estoque = estoque + ? WHERE id = ?")
            ->execute([(int)$s['qtd_consumida'], (int)$s['material_id']]);
      }
      $pdo->prepare("DELETE FROM venda_consumos WHERE venda_id = ?")->execute([$vendaId]);
    } else {
      // Fallback caso não haja snapshot
      $bomOld = $pdo->prepare("SELECT material_id, qtd_por_unidade FROM produto_bom WHERE produto_id = ?");
      $bomOld->execute([(int)$cur['produto_id']]);
      foreach ($bomOld->fetchAll() as $it) {
        $qtdDev = (int)ceil($it['qtd_por_unidade'] * (int)$cur['qtd']);
        $pdo->prepare("UPDATE materiais SET estoque = estoque + ? WHERE id = ?")
            ->execute([$qtdDev, (int)$it['material_id']]);
      }
    }

    // 2) Calcula novo consumo/custo
    $bomNew = $pdo->prepare("
      SELECT pb.material_id, pb.qtd_por_unidade, m.estoque, m.custo_medio
      FROM produto_bom pb
      JOIN materiais m ON m.id = pb.material_id
      WHERE pb.produto_id = ?
    ");
    $bomNew->execute([$novoProdutoId]);
    $newRows = $bomNew->fetchAll();
    if (!$newRows) throw new Exception('Produto (novo) sem BOM definida.');

    $custo_unit = 0.0;
    $consumosNovos = [];
    foreach ($newRows as $r) {
      $nec = (int)ceil($r['qtd_por_unidade'] * $novaQtd);
      if ((int)$r['estoque'] < $nec) throw new Exception('Estoque insuficiente do material ID '.$r['material_id'].' para a nova venda');
      $custo_unit += (float)$r['qtd_por_unidade'] * (float)$r['custo_medio'];
      $consumosNovos[] = ['mat'=>(int)$r['material_id'], 'qtd'=>$nec, 'cm'=>(float)$r['custo_medio']];
    }
    $lucro_unit = $novoPrecoUnit - $custo_unit;

    // 3) Aplica novos consumos + snapshot
    $insSnap = $pdo->prepare("
      INSERT INTO venda_consumos (venda_id, material_id, qtd_consumida, custo_unit_no_momento)
      VALUES (?, ?, ?, ?)
    ");
    foreach ($consumosNovos as $c) {
      $pdo->prepare("UPDATE materiais SET estoque = estoque - ? WHERE id = ?")
          ->execute([$c['qtd'], $c['mat']]);
      $insSnap->execute([$vendaId, $c['mat'], $c['qtd'], $c['cm']]);
    }

    // 4) Atualiza cabeçalho + item
    $pdo->prepare("
      UPDATE vendas
         SET data = ?, canal = ?, obs = ?, forma_pagamento = ?, pago = ?
       WHERE id = ?
    ")->execute([$novaData->format('Y-m-d H:i:s'), $canal, $obs, $forma_pagamento, $pago ? 1 : 0, $vendaId]);

    $pdo->prepare("
      UPDATE venda_itens
         SET produto_id = ?, qtd = ?, preco_unit = ?, custo_unit_calculado = ?, lucro_unit = ?
       WHERE venda_id = ?
    ")->execute([$novoProdutoId, $novaQtd, $novoPrecoUnit, $custo_unit, $lucro_unit, $vendaId]);

    // 5) Caixa: regrava movimento conforme "pago"
    caixa_apagar_por_ref('VENDA', 'vendas', $vendaId);
    if ($pago) {
      $st = $pdo->prepare("SELECT IFNULL(SUM(qtd*preco_unit),0) t FROM venda_itens WHERE venda_id = ?");
      $st->execute([$vendaId]);
      $total = (float)($st->fetch()['t'] ?? 0);
      caixa_lancar(new DateTime(), 'VENDA', 'vendas', $vendaId, $total, 'Venda #'.$vendaId.' (editada)');
    }

    $pdo->commit();
  } catch (Throwable $e) {
    $pdo->rollBack();
    throw $e;
  }
}

/* ============================================================
   EXCLUIR VENDA (estorna estoque e remove do caixa)
   ============================================================ */
function excluir_venda(int $vendaId): void {
  $pdo = db();
  $pdo->beginTransaction();
  try {
    $snap = $pdo->prepare("SELECT material_id, qtd_consumida FROM venda_consumos WHERE venda_id = ? FOR UPDATE");
    $snap->execute([$vendaId]);
    $snapRows = $snap->fetchAll();

    if ($snapRows) {
      foreach ($snapRows as $s) {
        $pdo->prepare("UPDATE materiais SET estoque = estoque + ? WHERE id = ?")
            ->execute([(int)$s['qtd_consumida'], (int)$s['material_id']]);
      }
      $pdo->prepare("DELETE FROM venda_consumos WHERE venda_id = ?")->execute([$vendaId]);
    } else {
      $it = $pdo->prepare("SELECT produto_id, qtd FROM venda_itens WHERE venda_id = ? FOR UPDATE");
      $it->execute([$vendaId]);
      $item = $it->fetch();
      if ($item) {
        $bom = $pdo->prepare("SELECT material_id, qtd_por_unidade FROM produto_bom WHERE produto_id = ?");
        $bom->execute([(int)$item['produto_id']]);
        foreach ($bom->fetchAll() as $r) {
          $qtdDev = (int)ceil($r['qtd_por_unidade'] * (int)$item['qtd']);
          $pdo->prepare("UPDATE materiais SET estoque = estoque + ? WHERE id = ?")
              ->execute([$qtdDev, (int)$r['material_id']]);
        }
      }
    }

    // Remove item e cabeçalho
    $pdo->prepare("DELETE FROM venda_itens WHERE venda_id = ?")->execute([$vendaId]);
    $pdo->prepare("DELETE FROM vendas WHERE id = ?")->execute([$vendaId]);

    // Remove do caixa
    caixa_apagar_por_ref('VENDA', 'vendas', $vendaId);

    $pdo->commit();
  } catch (Throwable $e) {
    $pdo->rollBack();
    throw $e;
  }
}

/* ============================================================
   PERDAS
   ============================================================ */
function registrar_perda_produto(
  DateTime $data,
  int $produtoId,
  int $qtd,
  ?string $motivo = null,
  ?string $obs = null
): int {
  $pdo = db();
  $pdo->beginTransaction();
  try {
    $bom = $pdo->prepare("
      SELECT pb.material_id, pb.qtd_por_unidade, m.estoque, m.custo_medio
      FROM produto_bom pb
      JOIN materiais m ON m.id = pb.material_id
      WHERE pb.produto_id = ?
    ");
    $bom->execute([$produtoId]);
    $rows = $bom->fetchAll();
    if (!$rows) throw new Exception('Produto sem BOM definida.');

    $consumos = [];
    $custo_total = 0.0;
    foreach ($rows as $r) {
      $cons = (int)ceil($r['qtd_por_unidade'] * $qtd);
      if ((int)$r['estoque'] < $cons) throw new Exception('Estoque insuficiente do material ID '.$r['material_id'].' para registrar perda');
      $consumos[] = ['mat'=>(int)$r['material_id'], 'qtd'=>$cons, 'cm'=>(float)$r['custo_medio']];
      $custo_total += $cons * (float)$r['custo_medio'];
    }

    $pdo->prepare("
      INSERT INTO perdas (data, tipo, produto_id, material_id, qtd, motivo, obs, custo_total)
      VALUES (?,?,?,?,?,?,?,?)
    ")->execute([$data->format('Y-m-d H:i:s'), 'PRODUTO', $produtoId, null, $qtd, $motivo, $obs, $custo_total]);
    $perdaId = (int)$pdo->lastInsertId();

    $insSnap = $pdo->prepare("
      INSERT INTO perda_consumos (perda_id, material_id, qtd_consumida, custo_unit_no_momento)
      VALUES (?,?,?,?)
    ");
    foreach ($consumos as $c) {
      $pdo->prepare("UPDATE materiais SET estoque = estoque - ? WHERE id = ?")->execute([$c['qtd'], $c['mat']]);
      $insSnap->execute([$perdaId, $c['mat'], $c['qtd'], $c['cm']]);
    }

    $pdo->commit();
    return $perdaId;
  } catch (Throwable $e) { $pdo->rollBack(); throw $e; }
}

function registrar_perda_material(
  DateTime $data,
  int $materialId,
  int $qtd,
  ?string $motivo = null,
  ?string $obs = null
): int {
  $pdo = db();
  $pdo->beginTransaction();
  try {
    $st = $pdo->prepare("SELECT estoque, custo_medio FROM materiais WHERE id=? FOR UPDATE");
    $st->execute([$materialId]);
    $m = $st->fetch();
    if (!$m) throw new Exception('Material não encontrado');
    if ((int)$m['estoque'] < $qtd) throw new Exception('Estoque insuficiente deste material para registrar perda');

    $custo_total = $qtd * (float)$m['custo_medio'];

    $pdo->prepare("
      INSERT INTO perdas (data, tipo, produto_id, material_id, qtd, motivo, obs, custo_total)
      VALUES (?,?,?,?,?,?,?,?)
    ")->execute([$data->format('Y-m-d H:i:s'), 'MATERIAL', null, $materialId, $qtd, $motivo, $obs, $custo_total]);

    $perdaId = (int)$pdo->lastInsertId();

    $pdo->prepare("UPDATE materiais SET estoque = estoque - ? WHERE id = ?")->execute([$qtd, $materialId]);

    $pdo->prepare("
      INSERT INTO perda_consumos (perda_id, material_id, qtd_consumida, custo_unit_no_momento)
      VALUES (?,?,?,?)
    ")->execute([$perdaId, $materialId, $qtd, (float)$m['custo_medio']]);

    $pdo->commit();
    return $perdaId;
  } catch (Throwable $e) { $pdo->rollBack(); throw $e; }
}

/* ============================================================
   CAIXA: utilitários
   ============================================================ */
function caixa_lancar(DateTime $data, string $tipo, ?string $ref_tabela, ?int $ref_id, float $valor, ?string $descricao = null): void {
  $pdo = db();
  $st = $pdo->prepare("INSERT IGNORE INTO caixa_movimentos (data, tipo, ref_tabela, ref_id, descricao, valor)
                       VALUES (?, ?, ?, ?, ?, ?)");
  $st->execute([$data->format('Y-m-d H:i:s'), $tipo, $ref_tabela, $ref_id, $descricao, $valor]);
}

function caixa_apagar_por_ref(string $tipo, ?string $ref_tabela, ?int $ref_id): void {
  $pdo = db();
  $st = $pdo->prepare("DELETE FROM caixa_movimentos WHERE tipo = ? AND ref_tabela <=> ? AND ref_id <=> ?");
  $st->execute([$tipo, $ref_tabela, $ref_id]);
}

function caixa_ajuste(DateTime $data, float $valor, ?string $descricao = null): void {
  caixa_lancar($data, 'AJUSTE', null, null, $valor, $descricao ?: 'Ajuste de caixa');
}

/* ============================================================
   Status de pagamento: VENDAS e COMPRAS
   ============================================================ */
function atualizar_status_pagamento(int $vendaId, int $pago): void {
  $pdo = db();
  $pdo->beginTransaction();
  try {
    $pdo->prepare("UPDATE vendas SET pago = ? WHERE id = ?")->execute([$pago ? 1 : 0, $vendaId]);

    $st = $pdo->prepare("SELECT IFNULL(SUM(qtd*preco_unit),0) t FROM venda_itens WHERE venda_id = ?");
    $st->execute([$vendaId]);
    $total = (float)($st->fetch()['t'] ?? 0);

    if ($pago) {
      caixa_lancar(new DateTime(), 'VENDA', 'vendas', $vendaId, $total, 'Venda #'.$vendaId);
    } else {
      caixa_apagar_por_ref('VENDA', 'vendas', $vendaId);
    }

    $pdo->commit();
  } catch (Throwable $e) { $pdo->rollBack(); throw $e; }
}

function atualizar_status_pagamento_compra(int $compraId, int $pago): void {
  $pdo = db();
  $pdo->beginTransaction();
  try {
    $pdo->prepare("UPDATE compras SET pago = ? WHERE id = ?")->execute([$pago ? 1 : 0, $compraId]);

    $st = $pdo->prepare("SELECT data, custo_total, IFNULL(frete,0) frete FROM compras WHERE id = ?");
    $st->execute([$compraId]);
    $c = $st->fetch();
    if (!$c) throw new Exception('Compra não encontrada');

    $valor = -1 * ((float)$c['custo_total'] + (float)$c['frete']); // saída (negativo)
    $data = new DateTime($c['data'] ?? 'now');

    if ($pago) {
      caixa_lancar($data, 'COMPRA', 'compras', $compraId, $valor, 'Compra #'.$compraId);
    } else {
      caixa_apagar_por_ref('COMPRA', 'compras', $compraId);
    }

    $pdo->commit();
  } catch (Throwable $e) { $pdo->rollBack(); throw $e; }
}
