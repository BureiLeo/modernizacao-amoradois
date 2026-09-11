<?php
/**
 * Script para criar tabela de clientes e cliente padrão
 * Execute uma vez no navegador: clientes_setup.php
 */
require_once __DIR__.'/db.php';
$pdo = db();

try {
    // Verificar se a tabela já existe
    $tabelaExiste = false;
    try {
        $pdo->query("SELECT 1 FROM clientes LIMIT 1");
        $tabelaExiste = true;
        echo "✓ Tabela 'clientes' já existe<br>";
    } catch (PDOException $e) {
        // Tabela não existe, vamos criar
    }
    
    if (!$tabelaExiste) {
        // Criar tabela de clientes do zero
        $pdo->exec("
            CREATE TABLE clientes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                codigo INT UNIQUE NOT NULL,
                nome VARCHAR(255) NOT NULL,
                telefone VARCHAR(20),
                email VARCHAR(255),
                cpf VARCHAR(14),
                rg VARCHAR(20),
                cnpj VARCHAR(18),
                razao_social VARCHAR(255),
                endereco TEXT,
                obs TEXT,
                criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_nome (nome),
                INDEX idx_codigo (codigo),
                INDEX idx_cpf (cpf),
                INDEX idx_cnpj (cnpj)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        
        echo "✓ Tabela 'clientes' criada com sucesso!<br>";
        
        // Criar cliente padrão
        $pdo->exec("
            INSERT INTO clientes (codigo, nome, telefone, obs) 
            VALUES (1, 'Consumidor Final', '', 'Cliente padrão para vendas sem cadastro específico')
        ");
        echo "✓ Cliente padrão 'Consumidor Final' criado com código #1!<br>";
    } else {
        // Tabela existe, verificar e adicionar colunas que não existem
        
        // Primeiro, verificar quais colunas já existem
        $colunasExistentes = [];
        $result = $pdo->query("SHOW COLUMNS FROM clientes");
        while ($row = $result->fetch()) {
            $colunasExistentes[] = $row['Field'];
        }
        
        echo "✓ Colunas existentes: " . implode(', ', $colunasExistentes) . "<br><br>";
        
        // Tratar coluna codigo especialmente
        if (!in_array('codigo', $colunasExistentes)) {
            try {
                // Adicionar coluna codigo temporariamente como nullable
                $pdo->exec("ALTER TABLE clientes ADD COLUMN codigo INT AFTER id");
                echo "✓ Coluna 'codigo' adicionada!<br>";
                
                // Gerar códigos para todos os clientes
                $clientes = $pdo->query("SELECT id FROM clientes ORDER BY id")->fetchAll();
                $codigo = 1;
                foreach ($clientes as $c) {
                    $pdo->prepare("UPDATE clientes SET codigo = ? WHERE id = ?")->execute([$codigo++, $c['id']]);
                }
                echo "✓ Códigos gerados para " . count($clientes) . " clientes!<br>";
                
                // Agora tornar a coluna UNIQUE e NOT NULL
                $pdo->exec("ALTER TABLE clientes MODIFY COLUMN codigo INT NOT NULL");
                $pdo->exec("ALTER TABLE clientes ADD UNIQUE KEY unique_codigo (codigo)");
                echo "✓ Coluna 'codigo' configurada como UNIQUE!<br>";
                
            } catch (PDOException $e) {
                echo "⚠️ Erro com coluna 'codigo': " . $e->getMessage() . "<br>";
                
                // Tentar corrigir se já existe mas com valores ruins
                try {
                    // Remover valores duplicados/nulos
                    $clientes = $pdo->query("SELECT id FROM clientes ORDER BY id")->fetchAll();
                    $codigo = 1;
                    foreach ($clientes as $c) {
                        $pdo->prepare("UPDATE clientes SET codigo = ? WHERE id = ?")->execute([$codigo++, $c['id']]);
                    }
                    echo "✓ Códigos corrigidos para " . count($clientes) . " clientes!<br>";
                    
                    // Tentar adicionar constraint UNIQUE
                    try {
                        $pdo->exec("ALTER TABLE clientes ADD UNIQUE KEY unique_codigo (codigo)");
                        echo "✓ Coluna 'codigo' configurada como UNIQUE!<br>";
                    } catch (PDOException $e2) {
                        echo "⚠️ Não foi possível adicionar UNIQUE: " . $e2->getMessage() . "<br>";
                    }
                } catch (PDOException $e3) {
                    echo "⚠️ Erro ao corrigir códigos: " . $e3->getMessage() . "<br>";
                }
            }
        } else {
            echo "⚠️ Coluna 'codigo' já existe<br>";
        }
        
        // Adicionar outras colunas
        $outrasColunas = [
            'cpf' => 'VARCHAR(14)',
            'rg' => 'VARCHAR(20)',
            'cnpj' => 'VARCHAR(18)',
            'razao_social' => 'VARCHAR(255)'
        ];
        
        foreach ($outrasColunas as $nomeCol => $tipo) {
            if (!in_array($nomeCol, $colunasExistentes)) {
                try {
                    $pdo->exec("ALTER TABLE clientes ADD COLUMN $nomeCol $tipo");
                    echo "✓ Coluna '$nomeCol' adicionada!<br>";
                } catch (PDOException $e) {
                    echo "⚠️ Erro ao adicionar coluna '$nomeCol': " . $e->getMessage() . "<br>";
                }
            } else {
                echo "⚠️ Coluna '$nomeCol' já existe<br>";
            }
        }
        
        // Verificar se cliente padrão já existe (agora que codigo está OK)
        $check = $pdo->query("SELECT id, codigo FROM clientes WHERE nome = 'Consumidor Final'")->fetch();
        
        if (!$check) {
            // Pegar próximo código disponível
            $maxCodigo = $pdo->query("SELECT IFNULL(MAX(codigo), 0) as m FROM clientes")->fetch()['m'];
            $novoCodigo = $maxCodigo + 1;
            
            $pdo->prepare("
                INSERT INTO clientes (codigo, nome, telefone, obs) 
                VALUES (?, 'Consumidor Final', '', 'Cliente padrão para vendas sem cadastro específico')
            ")->execute([$novoCodigo]);
            echo "✓ Cliente padrão 'Consumidor Final' criado com código #$novoCodigo!<br>";
        } else {
            echo "✓ Cliente padrão 'Consumidor Final' já existe (ID: {$check['id']}, Código: #{$check['codigo']})<br>";
        }
    }
    
    // Adicionar coluna cliente_id na tabela vendas (se não existir)
    try {
        $pdo->exec("
            ALTER TABLE vendas 
            ADD COLUMN cliente_id INT DEFAULT NULL AFTER data,
            ADD INDEX idx_cliente (cliente_id)
        ");
        echo "✓ Coluna 'cliente_id' adicionada à tabela 'vendas'!<br>";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            echo "⚠️ Coluna 'cliente_id' já existe na tabela vendas<br>";
        } else {
            echo "⚠️ Erro na tabela vendas: " . $e->getMessage() . "<br>";
        }
    }
    
    echo "<br><strong>✅ Setup de clientes concluído com sucesso!</strong><br>";
    echo "<a href='vendas_nova_v3.php'>Ir para Nova Venda</a>";
    
} catch (PDOException $e) {
    echo "❌ Erro fatal: " . $e->getMessage();
}
?>
