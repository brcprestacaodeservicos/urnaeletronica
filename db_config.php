<?php
    include 'admin.php';
// =================================================================
// 🔑 Detalhes da Conexão MySQL (InfinityFree)
// =================================================================
define('DB_HOST', 'sql308.infinityfree.com');
define('DB_USER', 'if0_40316066');
define('DB_PASS', 'Azbox286');
define('DB_NAME', 'if0_40316066_urnaeletronica');

// Nomes das tabelas no seu banco de dados
define('TABLE_CONFIG', 'config'); 
define('TABLE_VOTOS', 'votos'); 

// Função para estabelecer a conexão com o BD
function conectar_db() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        die("Falha na conexão com o Banco de Dados: " . $conn->connect_error);
    }
    $conn->set_charset("utf8mb4");
    return $conn;
}

// =================================================================
// 🗳️ Funções de Votação e Status
// =================================================================

/**
 * Registra um voto na tabela 'votos'.
 * @param string $candidato O nome/código do candidato.
 * @return bool True se o registro foi bem-sucedido.
 */
function registrar_voto($candidato) {
    $conn = conectar_db();
    
    // Query preparada para segurança contra SQL Injection
    $stmt = $conn->prepare("INSERT INTO " . TABLE_VOTOS . " (candidato, timestamp) VALUES (?, NOW())");
    $stmt->bind_param("s", $candidato);
    
    $sucesso = $stmt->execute();
    
    $stmt->close();
    $conn->close();
    
    return $sucesso;
}

/**
 * Verifica se a votação está ATIVA ou ENCERRADA.
 * @return bool True se estiver ATIVA.
 */
function verificar_status_votacao() {
    $conn = conectar_db();
    
    $query = "SELECT valor FROM " . TABLE_CONFIG . " WHERE chave = 'status_votacao'";
    $resultado = $conn->query($query);
    
    if ($resultado && $resultado->num_rows > 0) {
        $linha = $resultado->fetch_assoc();
        $status = $linha['valor'];
        $conn->close();
        
        return strtoupper($status) === 'ATIVA';
    }
    
    $conn->close();
    return false; // Retorna false se o status não puder ser lido
}

/**
 * Altera o status da votação para 'ENCERRADA'.
 * @return bool True se a atualização foi bem-sucedida.
 */
function encerrar_votacao_no_db() {
    $conn = conectar_db();
    
    $stmt = $conn->prepare("UPDATE " . TABLE_CONFIG . " SET valor = 'ENCERRADA' WHERE chave = 'status_votacao'");
    $sucesso = $stmt->execute();
    
    $stmt->close();
    $conn->close();
    
    return $sucesso;
}

// =================================================================
// 📊 Funções de Relatório (Completa)
// =================================================================

/**
 * Consulta o BD e retorna todos os dados para o relatório final.
 * @return array Dados estruturados do relatório.
 */
function gerar_relatorio_dados() {
    $conn = conectar_db();
    $dados = [
        'candidatos' => [], 
        'votos_em_branco' => 0,
        'status_votacao' => 'ATIVA', 
        'data_encerramento' => 'N/A'
    ];
    
    // A. Contagem de Votos por Candidato (incluindo EmBranco)
    $query_contagem = "SELECT candidato, COUNT(*) as total FROM " . TABLE_VOTOS . " GROUP BY candidato ORDER BY total DESC";
    $resultado = $conn->query($query_contagem);

    if ($resultado) {
        while ($linha = $resultado->fetch_assoc()) {
            // Usa 'trim' e 'strtolower' para garantir a correspondência com 'embranco'
            if (strtolower(trim($linha['candidato'])) === 'embranco') {
                $dados['votos_em_branco'] = (int)$linha['total'];
            } else {
                $dados['candidatos'][htmlspecialchars($linha['candidato'])] = (int)$linha['total'];
            }
        }
    }
    
    // B. Obter Status da Votação
    $query_status = "SELECT valor FROM " . TABLE_CONFIG . " WHERE chave = 'status_votacao'";
    $resultado_status = $conn->query($query_status);
    if ($resultado_status && $resultado_status->num_rows > 0) {
        $linha_status = $resultado_status->fetch_assoc();
        $dados['status_votacao'] = strtoupper($linha_status['valor']);
        
        // C. Se estiver ENCERRADA, buscar a data do último voto (simulação de encerramento)
        if ($dados['status_votacao'] === 'ENCERRADA') {
            $query_data = "SELECT timestamp FROM " . TABLE_VOTOS . " ORDER BY timestamp DESC LIMIT 1"; 
            $resultado_data = $conn->query($query_data);
            if ($resultado_data && $resultado_data->num_rows > 0) {
                $dados['data_encerramento'] = date('d/m/Y H:i:s', strtotime($resultado_data->fetch_assoc()['timestamp']));
            }
        }
    }

    $conn->close();
    return $dados;
}
?>