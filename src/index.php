<?php

namespace RafaelDuarte\OlhoVivo;
error_reporting(E_ALL);
ini_set('display_errors', 1);


require_once dirname(__DIR__) . '/vendor/autoload.php';


use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Dotenv\Dotenv;


class OlhoVivo
{
    public string $token;
    public string $url;
    public $versao = "v2.1/"; // Versão da api olho vivo
    private $autenticado = false;
    private $client;

    public function __construct()
    {
        $dotenv = Dotenv::createImmutable(dirname(__DIR__));

        $dotenv->load();

        $this->url = $_ENV['SP_TRANS_API_ENDPOINT'] ?? env('SP_TRANS_API_ENDPOINT');

        $this->token = $_ENV['SP_TRANS_API_KEY'] ?? env('SP_TRANS_API_KEY');

        if (!empty($this->token)) {
            $this->autenticar();
        }
    }

    public function autenticar() // Autenticar usuário na API
    {
        $return = false;
        try {
            $this->client = new Client([
                'base_uri' => $this->url . $this->versao,
                'timeout' => 2.0,
                'cookies' => true,
                'decode_content' => false
            ]); // Inicia um Client na URL informada da api

            $login = $this->client->request(
                'POST',
                'Login/Autenticar',
                ['query' => ['token' => $this->token]]
            ); // Realiza a request de autenticação com o token de aplicativo
            if (!(json_decode($login->getBody()))) { // Retorna true para autenticado e false para não autenticado
                throw new \Exception("Erro ao autenticar com este token.");
            } elseif (!($login->hasHeader('Set-Cookie'))) {
                throw new \Exception("O servidor não está configurado para definir as credenciais necessárias para a autenticação do usuário.");
            }
            if ($login->getBody()) {
                $this->autenticado = true;
                $return = $login->getBody();
            }
        } catch (RequestException $e) {
            throw new \Exception("HTTP request/response erro: {$e->getMessage()}");
        } finally {
            return $return;
        }
    }

    public function buscarCorredores()
    {
        return json_decode(json_encode($this->execute($this->url . $this->versao . 'Corredor')), false);
    }

    private function execute($uri, $params = [], bool $decodeAsJson = true)
    { // Executar requisição via GET para os endpoints da API
        if (!$this->autenticado) {
            return 'Você não está autenticado';
        }
        try {
            do {
                $request = ($this->client->request(
                    'GET',
                    $uri,
                    count($params) > 0 ? ['query' => $params] : []
                ))->getBody();
                $decoded = json_decode($request, true);
            } while (isset($decoded['Message']));
            return $decodeAsJson === true ? $decoded : $request;
        } catch (RequestException $e) {
            throw new \Exception("HTTP request/response error: {$e->getMessage()}");
        }
    }

    public function buscarEmpresas()
    {
        return json_decode(json_encode($this->execute($this->url . $this->versao . 'Empresa')), false);
    }

    public function buscarParadasPorLinha(string|int $codigoLinha)
    {
        if (!$this->verificarCodigos($codigoLinha)) {
            return 'O código deve ter apenas caracteres númericos!';
        }
        $queryParams = [
            'codigoLinha' => intval($codigoLinha),
        ];
        return json_decode(json_encode($this->execute($this->url . $this->versao . 'Parada/BuscarParadasPorLinha', $queryParams)), false);
    }

    public function verificarCodigos(...$codigos)
    {
        foreach ($codigos as $codigo) {
            if (!is_numeric($codigo)) {
                return false; // retorna falso se algum parâmetro não for número
            }
        }
        return true; // retorna verdadeiro se todos os parâmetros forem números
    } // Buscar as paradas expecíficas de São Paulo

    public function buscarParadasPorCorredor(string|int $codigoCorredor)
    {
        if (!$this->verificarCodigos($codigoCorredor)) {
            return 'O código deve ter apenas caracteres númericos!';
        }
        $queryParams = [
            'codigoCorredor' => intval($codigoCorredor),
        ];
        return json_decode(json_encode($this->execute($this->url . $this->versao . 'Parada/BuscarParadasPorCorredor', $queryParams)), false);
    } // Buscar todos os corredores de São Paulo

    public function buscarPosicaoTodosOnibus()
    {
        return json_encode($this->execute($this->url . $this->versao . 'Posicao'));
    } // Buscar todas as empresas operadoras do transporte público de São Paulo

    public function buscarPosicaoOnibusEspecifico(string|int $codigoLinha)
    {
        if (!$this->verificarCodigos($codigoLinha)) {
            return 'O código deve ter apenas caracteres númericos!';
        }
        $queryParams = [
            'codigoLinha' => intval($codigoLinha),
        ];
        return json_decode(json_encode($this->execute($this->url . $this->versao . 'Posicao/Linha', $queryParams)), false);
    } // Buscar as paradas por linhas de São Paulo

    public function buscarVeiculosGaragem(string|int $codigoEmpresa, string|int $codigoLinha)
    {
        if (!$this->verificarCodigos($codigoEmpresa, $codigoLinha)) {
            return 'O código deve ter apenas caracteres númericos!';
        }
        $queryParams = [
            'codigoEmpresa' => intval($codigoEmpresa),
            'codigoLinha' => intval($codigoLinha),
        ];
        return json_decode(json_encode($this->execute($this->url . $this->versao . 'Posicao/Garagem', $queryParams)), false);
    } // Buscar as paradas de São Paulo por corredor

    public function buscarPrevisaoChegadaParadaLinha(string|int $codigoParada, string|int $codigoLinha)
    {
        if (!$this->verificarCodigos($codigoParada, $codigoLinha)) {
            return 'O código deve ter apenas caracteres númericos!';
        }
        $queryParams = [
            'codigoParada' => intval($codigoParada),
            'codigoLinha' => intval($codigoLinha),
        ];
        return json_decode(json_encode($this->execute($this->url . $this->versao . 'Previsao', $queryParams)), false);
    } // Buscar as posições de todos os ônibus de de São Paulo

    public function buscarPrevisaoChegadaParada(string|int $codigoParada)
    {
        if (!$this->verificarCodigos($codigoParada)) {
            return 'O código deve ter apenas caracteres númericos!';
        }
        $queryParams = [
            'codigoParada' => intval($codigoParada),
        ];
        return json_decode(json_encode($this->execute($this->url . $this->versao . 'Previsao/Parada', $queryParams)), false);
    } // Buscar a posição de ônibus de linhas específicas de São Paulo

    public function buscarMapa($rota = '')
    {
        return $this->executeObterKMZ($rota);
    } // Buscar os veículos em garagem de empresas específicas com base ou não na linha (opcional)

    private function executeObterKMZ(string $rota = '')
    {
        $res = $this->client->request('GET', $this->url . $this->versao . 'KMZ' . $rota, [
            'headers' => [
                'Accept-Encoding' => 'gzip',
                'Content-Type' => 'application/vnd.google-earth.kmz'
            ],
            'stream' => true
        ]);

        // Verifica se a resposta foi bem-sucedida
        if ($res->getStatusCode() == 200) {
            // Salva o conteúdo do arquivo KMZ
            file_put_contents('mapa.kmz', $res->getBody());
            return true;
        } else {
            return false;
        }
    } // Buscar a previsao de chegada de paradas específicas para linhas específicas de São Paulo

    public function espBuscarChegadasLinhaParadas(string|int $linha, string|int $parada)
        // Busca especializada das chegadas de uma linha em uma parada
    {
        if (!$this->autenticado) {
            return 'Você não está autenticado';
        }
        $return = false;
        $jsonArray = array();
        if (empty($linha) || empty($parada)) {
            $return = 'empty';
        } else {
            try { // Tenta captar as informações necessárias para a verificação
                $linhasInfo = $this->buscarLinhas($linha)[0];
                $linhaCod = intval($this->buscarLinhas($linha)[0]->cl);
                $parada = intval($this->buscarParadas($parada)[0]['cp']);
                $prevChegadaLinha = $this->buscarPrevisaoChegadaLinha($linhaCod);
            } catch (\Exception $e) {
                $return = 'invalid';
            }
            if ($return != 0) {
                return json_encode($return);
            } else {
                /*
                    Faz as verificações, na qual, quando o código de uma parada da linha informada for igual ao código
                    da parada informada, é retornada todas as informações de ambas em um mesmo json.
                */
                $i = 0;
                while ($i <= count($prevChegadaLinha->ps) - 1) {
                    if (intval($prevChegadaLinha->ps[$i]->cp) == $parada) {
                        $chegadaLinha = $prevChegadaLinha->ps[$i];
                        array_push($jsonArray, $linhasInfo);
                        array_push($jsonArray, $chegadaLinha);
                        $jsonArray = array(
                            "linha" => $jsonArray[0],
                            "chegada" => $jsonArray[1],
                        );
                        return json_decode(json_encode($jsonArray), false);
                    }
                    $i++;
                }
            }
        }
        return $return;
    } // Buscar a previsao de chegada de linhas específicas em todas as paradas que ela abrange de São Paulo

    public function buscarLinhas(string|int $linha)
    { // Buscar as linhas expecíficas de São Paulo
        $queryParams = [
            'termosBusca' => $linha,
        ];
        return json_decode(json_encode($this->execute($this->url . $this->versao . 'Linha/Buscar', $queryParams)), false);
        // Retorna um objeto
    } // Buscar a previsao de chegada de paradas específicas em todas as linhas que ela abrange de São Paulo

    public function buscarParadas(string $endereco)
    {
        $queryParams = [
            'termosBusca' => $endereco,
        ];
        return $this->execute($this->url . $this->versao . 'Parada/Buscar', $queryParams);
    } // Busca o mapa geral, de corredores e de outras vias de SP (/Corredor, /OutrasVias)


    // FUNÇÕES ESPECIALIZADAS (PODEM SER MAIS PRECISAS)

    public function buscarPrevisaoChegadaLinha(string|int $codigoLinha)
    {
        if (!$this->verificarCodigos($codigoLinha)) {
            return 'O código deve ter apenas caracteres númericos!';
        }
        $queryParams = [
            'codigoLinha' => intval($codigoLinha),
        ];
        return json_decode(json_encode($this->execute($this->url . $this->versao . 'Previsao/Linha', $queryParams)), false);
    }
}

