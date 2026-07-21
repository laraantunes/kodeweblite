<?php
require_once __DIR__ . '/base.php';

try {
    switch ($action) {
        case 'update_kodeweb':
            // Buscar a última release no repositório laraantunes/kodeweblite
            $ch = curl_init('https://api.github.com/repos/laraantunes/kodeweblite/releases/latest');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_USERAGENT, 'KodeWebLite-Updater');
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            $apiResponse = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if (!$apiResponse || $httpCode !== 200) {
                throw new Exception("Falha ao buscar a última versão no GitHub. HTTP: $httpCode");
            }
            
            $releaseData = json_decode($apiResponse, true);
            $assets = $releaseData['assets'] ?? [];
            
            $zipUrl = '';
            foreach ($assets as $asset) {
                if (strpos($asset['name'], 'kodeweb') !== false && strpos($asset['name'], '.zip') !== false) {
                    $zipUrl = $asset['browser_download_url'];
                    break;
                }
            }
            
            if (empty($zipUrl)) {
                // Fallback to source zip if release asset doesn't explicitly match
                $zipUrl = $releaseData['zipball_url'] ?? '';
                if (empty($zipUrl)) {
                    throw new Exception("Nenhum arquivo de build (.zip) encontrado na última versão.");
                }
            }
            
            $tempZip = $rootDir . '/kodeweblite-release-temp.zip';
            
            // Usando cURL para baixar o ZIP
            $ch = curl_init($zipUrl);
            $fp = fopen($tempZip, 'w+');
            curl_setopt($ch, CURLOPT_FILE, $fp);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_USERAGENT, 'KodeWebLite-Updater');
            curl_setopt($ch, CURLOPT_TIMEOUT, 60); // 60 segundos max para baixar
            curl_exec($ch);
            $curlError = curl_error($ch);
            curl_close($ch);
            fclose($fp);
            
            if ($curlError || filesize($tempZip) < 1024) {
                if (file_exists($tempZip)) unlink($tempZip);
                throw new Exception("Falha ao baixar o arquivo da versão. Erro: $curlError");
            }
            
            $zip = new ZipArchive;
            if ($zip->open($tempZip) === TRUE) {
                $zip->extractTo($rootDir);
                $zip->close();
                unlink($tempZip);
                echo json_encode(['success' => true, 'message' => 'Aplicação atualizada para a ' . $releaseData['tag_name']]);
            } else {
                if (file_exists($tempZip)) unlink($tempZip);
                throw new Exception("Falha ao extrair o arquivo .zip da atualização.");
            }
            break;

        default:
            throw new Exception("Ação não reconhecida.");
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
