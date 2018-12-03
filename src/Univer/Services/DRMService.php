<?php

namespace Univer\Services;


use App\Univer\Services\GeolocationService;
use App\User;
use Carbon\Carbon;
use Httpful\Request;
use App\DrmPolicies;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Univer\Contracts\iMediaInterface;
use Univer\Entities\Video;
use Univer\Entities\BuyRent;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use App\Univer\Services\AccessControlService;
use Univer\Exceptions\RegionBlockedException;

class DRMService
{
    /**
     * @var string
     */
    protected $drmUrl;

    /**
     * @var string
     */
    protected $drmKeyForUser;

    /**
     * Este campo serve para que possa ser enviado o perfil via background sem a nescessidade de usar session, ele é setado no metodo login
     * @var integer
     */
    protected $perfilId;

    /**
     * DRMService constructor.
     */
    public function __construct()
    {
        $this->perfilId = false;
        if (app()->environment() === 'staging') {
            $this->drmUrl = 'https://sambatech.live.ott.irdeto.com/';
        } else {
            $this->drmUrl = 'http://sambatech.live.ott.irdeto.com/';
        }
    }

    /**
     * Create Session user in DRM.
     *
     * @param $user User Object
     * @todo Revisar try count e fluxo de exceções
     * @return \stdClass User Class .
     */
    public function login($user, $perfilId = false, $userIp = false)
    {
        try {
            if($perfilId){
                $this->perfilId = $perfilId;
            }

            $key = $this->getDrmKeyForUser($user);

            $this->deleteSession($key);

            /**
             * Parametros
             * sessiontime = tempo da sessão em segundos
             * Salva uma sessão por 4 horas.
             */

            if(!$userIp){
                $userIp = AccessControlService::getClientIP();
            }

            $url = $this->drmUrl."services/CreateSession?CrmId=sambatech&SessionTime=14400&UserId=".$key."&CreateUser=true".'&AccountId='.$key."&UserIp=".$userIp. "&Overwrite=true";

            $response = Request::get($url)
                ->addHeaders(array(
                    'MAN-user-id' => 'app@sambatech.com',
                    'MAN-user-password' => 'c5kU6DCTmomi9fU',
                ))
                ->send();

            $responseXML = new \SimpleXMLElement($response);

            $obj = new \stdClass();
            $obj->status = true;
            $obj->hash = (string)$responseXML[0]['SessionId'];
            $obj->ticket = (string)$responseXML[0]['Ticket'];
            $obj->session_expiration = Carbon::now()->addHours(3)->addMinutes(59)->timestamp;


            //Salva token DRM na session.
            return \Cache::remember($key, 179, function () use ($obj) {
                return $obj;
            });
        } catch (\Exception $e) {
            $arr =[];
            if(isset($url)){
                $arr['url'] = $url;
            }
            if (isset($response)) {
                $arr['response'] = $response;
            }
            $this->handleException("DRM: falha ao efetuar login do usuário", $e, $arr);
        }
    }

    private function setDrmKeyForUser($user=null)
    {
        if(!$user){
            $user = $this->getUserObject();
        }

        $key = $this->getUniqueUserKey($user);
        $this->drmKeyForUser = $key . '_drm';

        return $this->drmKeyForUser;
    }

    public function getDrmKeyForUser($user = null)
    {
        if(strlen($this->drmKeyForUser) > 0){
            return $this->drmKeyForUser;
        } else{
            return $this->setDrmKeyForUser($user);
        }
    }

    /**
     * Deleta uma session na irdeto
     * Limpa o objeto DRM do cache
     * @param $user
     */
    private function deleteSession($user)
    {
        $session = Cache::get($user);

        if($session && isset($session->hash) && strlen($session->hash) >0){
            $url = $this->drmUrl."services/DeleteSession?CrmId=sambatech&SessionId=".$session->hash;

            try{

                $response = Request::get($url)
                    ->addHeaders(array(
                        'MAN-user-id' => 'app@sambatech.com',
                        'MAN-user-password' => 'c5kU6DCTmomi9fU',
                    ))
                    ->send();
            } catch(\Exception $ex){
                // Falha ao deletar session.
                // De qualquer forma o método login usa o parametro overwrite =true
            }

            Cache::forget($user);
        }

    }

    /**
     * Caso exista o $_GET['debug'], limpa a session DRM
     * @param null $key
     */
    private function clearDrmCache($key=null)
    {
        if(isset($_GET['debug'])){
            if(!$key){
                $key = $this->getDrmKeyForUser($this->getUserObject());
            }

            if (Cache::has($key)) {
                Cache::forget($key);
            }
        }
    }

    /**
     * @param null $title
     * @param \Exception $e
     * @throws \Exception
     */
    private function handleException($title = null, \Exception $e, $arrayException)
    {
        if (app()->environment() === 'staging') {
            throw $e;
        } else {
            $titulo = $title ? $title : $e->getMessage();

            //Adiciona mais informaçoes no log que será efetuado
            $arr = [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ];
            foreach ($arrayException as $key => $value) {
                $arr[$key] = $value;
            }

            \Log::error($titulo, $arr);
        }
    }

    /**
     * Retorna a chave DRM para ser utilizada pelo usuário (combinação de email e perfil)
     * @param $objUser
     * @return string
     * @throws \Exception
     */
    private function getUniqueUserKey($objUser)
    {
        if (!$objUser->email) {
            throw new \Exception("Usuário enviado não possui atributo e-mail");
        }

        //$objUser pode ser um stdClass ou o usuário logado
        if ($objUser instanceof Model) {
            if (!$this->perfilId && !session("perfil") && !defined('ID_PERFIL')) {
                // Usuário precisa estar logado para assistir um vídeo e ser autorizado
                throw new \Exception("Falha ao localizar ID do PERFIL");
            }

            if($this->perfilId){
                $perfilId = $this->perfilId;
            }else{
                $perfilId = session('perfil') ? session('perfil') : ID_PERFIL;
            }

            return $objUser->email . '_p' . $perfilId;
        }

        // Caso não exista usuário logado, o atributo e-mail abaixo é um mock
        // o real valor de $objUser->email abaixo é o DEVICE_ID enviado em request da API
        return $objUser->email;

    }

    /**
     * Retorna somente a session DRM aberta e gravada no cache - não cria uma nova session.
     * Utilizar somente no front end - não considera DEVICE ID.
     * @param $forceCreate boolean Indica se o DRM deve forçar a criação de uma nova session.
     * @return mixed
     */
    public function getUserSession($forceCreate = false)
    {
        if (Auth::check()) {
            $key = $this->getDrmKeyForUser();

            //Temporario -> REMOVER APOS TESTES
            return $this->login($this->getUserObject());

            if (Cache::has($key)) {
                return (Cache::get($key));
            } else{

                if($forceCreate){
                    return $this->login($this->getUserObject());
                } else{
                    $obj = new \stdClass();
                    $obj->hash = null;
                    $obj->ticket = null;
                    return $obj;
                }
            }
        } else {
            // Redirect não funciona em alguns contextos
            // Deverá jogar uma exception
//            return redirect('/')->withError("Você não está logado");
        }
    }

    /**
     * Authorize user for content in IRDETO.
     *
     * @param $sessionId String Session Id from user.
     * @param $idSambaVdeos String String with contentId.
     * @param $authorizeType String svod|tvod String para identificar o tipo da URL a ser utilizada
     * @throws \Exception if the provided error.
     *
     * @return boolean Returns.
     */
    public function authorize($idSambaVdeos, $sessionId, $policyId, $authorizeAsTvod)
    {
        try {
            $url = $this->getAuthorizeUrl($idSambaVdeos, $policyId, $sessionId, $authorizeAsTvod);

            $response = Request::get($url)
                ->addHeaders(array(
                    'MAN-user-id' => 'app@sambatech.com',
                    'MAN-user-password' => 'c5kU6DCTmomi9fU',
                ))
                ->send();

            $this->monitorTicket($response);

            if ((int)$response->code != 200) {
                throw new \Exception(json_encode($response->raw_headers));
            }
            return true;
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }

    /**
     * Monitora respostas com o node Session e compara o valor do atributo Ticket
     * com o ticket salvo na sessão.
     *
     * Se diferente, atualiza a session do usuário.
     *
     * @param $response
     */
    public function monitorTicket($response){
        if(!$response instanceof  \SimpleXMLElement){
            return;
        }

        $sessionInfo = $response->xpath('//Session');

        if(!empty($sessionInfo)){

            $ticket = ((string)$sessionInfo[0]['Ticket']);
            $session = ((string)$sessionInfo[0]['SessionId']);

            $key = $this->getDrmKeyForUser();
            $drmSession = \Cache::get($key);

            if($drmSession && ($drmSession->ticket !== $ticket || $drmSession->hash !== $session)){

                \Log::info("DRM TICKET OR SESSION CHANGED",[
                    'key'=>$key,
                    'ticket_before'=>$drmSession->ticket,
                    'ticket_after'=>$ticket,
                    'session_before'=>$drmSession->hash,
                    'session_after'=>$session
                ]);

                // Atualiza ticket e session
                $drmSession->hash = $session;
                $drmSession->ticket = $ticket;
                $minuteDiff = Carbon::now()->diffInMinutes(Carbon::createFromTimestamp($drmSession->session_expiration));

                // Se faltar 5 minutos para a session expirar, retorna session valida por apenas 1 minuto, forçando
                // o DRM Service a renovar session do usuário no proximo request.
                if($minuteDiff < 5){
                    $minuteDiff = 1;
                }
                \Cache::forget($key);
                //Salva token DRM na session.
                return (\Cache::remember($key, $minuteDiff, function () use ($drmSession) {
                    return $drmSession;
                }));
            }
        }
    }

    /**
     * @return mixed
     */
    public function getCurrentSession()
    {
        if (!Auth::check() && !defined('DEVICE_ID')) {
            return null;
        }

        $sessionKey = $this->getDrmKeyForUser();
        $this->clearDrmCache($sessionKey);

        // Verifica se a session já existe e é valida
        $session = \Cache::get($sessionKey);

        if ($session && $this->sessionIsValid($session)) {
            return $session;
        } else {
            return $this->login($this->getUserObject());
        }
    }

    private function getCurrentSessionId()
    {
        if ($session = $this->getCurrentSession()) {
            return $session->hash;
        }
        return false;
    }

    private function getSessionKey()
    {
        if (Auth::check()) {
            $user = Auth::user();
            $key = $this->getUniqueUserKey($user);
            $key = $key . '_drm';
        } elseif (defined('DEVICE_ID')) {
            $key = DEVICE_ID . "_drm";
        }
        return $key;

    }

    /**
     * Retorna a instancia correta de um usuário para autenticação via DRM.
     * @return \stdClass|User
     */
    private function getUserObject()
    {
        if (Auth::check()) {
            return Auth::user();
        } elseif (defined('DEVICE_ID')) {
            $user = new \stdClass();
            $user->email = DEVICE_ID;
            return $user;
        } else{
            \Log::error("Falha ao localizar objeto do usuário");
//            redirect("/");
            // Redirect não funciona em alguns contextos. deverá jogar exception
        }
    }


    /**
     * Verifica se uma sessão é valida e está dentro do prazo de expiração
     * @param $session
     * @return bool
     */
    private function sessionIsValid($session)
    {
        return $session && property_exists($session, 'session_expiration') && $session->session_expiration > Carbon::now()->timestamp;
    }

    public function getTesteDRM()
    {
        // Make a request to the GitHub API with a custom
        // header of "X-Trvial-Header: Just as a demo".
        $url = $this->drmUrl . "/services/ActiveSessions?CrmId=sambatech";
        $response = Request::get($url)
            ->expectsXml()
            ->addHeaders(array(
                'MAN-user-id' => 'app@sambatech.com',              // Or add multiple headers at once
                'MAN-user-password' => 'c5kU6DCTmomi9fU',              // in the form of an assoc array
            ))
            ->send();

        echo $response;

    }

    public function registerUser()
    {
        $url = $this->drmUrl . "/services/CreateSession?CrmId=sambatech&UserId=user1@demo.com&CreateUser=true";
        $response = Request::get($url)
            ->addHeaders(array(
                'MAN-user-id' => 'app@sambatech.com',
                'MAN-user-password' => 'c5kU6DCTmomi9fU',
            ))
            ->send();

        $responseXML = new \SimpleXMLElement($response);
        echo $responseXML[0]['SessionId'];
    }

    public function getUser()
    {
        $url = $this->drmUrl . "/services/UserData?CrmId=sambatech&UserId=user@demo.com&UserEventList=true&UserLicenseList=true&CompatibilityLevel=2";
        $response = Request::get($url)
//            ->expectsXml()
            ->addHeaders(array(
                'MAN-user-id' => 'app@sambatech.com',
                'MAN-user-password' => 'c5kU6DCTmomi9fU',
            ))
            ->send();

        echo $response;
    }

    public function checkUsersAuth()
    {

        $url = $this->drmUrl . "/services/QuerySessionAuthorization?AccountId=sambatech&CrmId=sambatech&ContentId=a64270b79483245c8de500d34c90397d&SessionId=549FD0A5BE9B6472";
        $response = Request::get($url)
//            ->expectsXml()
            ->addHeaders(array(
                'MAN-user-id' => 'app@sambatech.com',
                'MAN-user-password' => 'c5kU6DCTmomi9fU',
            ))
            ->send();

        try {
            $responseXML = new \SimpleXMLElement($response);

            $this->monitorTicket($responseXML);

            return $responseXML[0]['Authorized'] === 'true' ? true : false;
        } catch (\Exception $ex) {
            return false;
        }

        var_dump($response);
        die();
    }

    public function checkSessionAuth($contentId, $sessionId)
    {
        $objReturn = new \stdClass();
        $objReturn->status = false;
        $objReturn->error = null;

        $url = $this->drmUrl . "/services/QuerySessionAuthorization?AccountId=sambatech&CrmId=sambatech&ContentId=" . $contentId . "&SessionId=" . $sessionId;

        $response = Request::get($url)
            ->addHeaders(array(
                'MAN-user-id' => 'app@sambatech.com',
                'MAN-user-password' => 'c5kU6DCTmomi9fU',
            ))
            ->send();

        try {
            $responseXML = new \SimpleXMLElement($response);
            $this->monitorTicket($responseXML);

            $auth = (string)$responseXML[0]['Authorized'];

            if(isset($responseXML[0]['ErrorCode'])) {
                $objReturn->error = (int)$responseXML[0]['ErrorCode'];
            }

            $objReturn->status = $auth === 'true' ? true : false;
            return $objReturn;

        } catch (\Exception $ex) {
            $objReturn->status = false;

            if(isset($responseXML[0]['ErrorCode'])){
                $objReturn->error = (int)$responseXML[0]['ErrorCode'];
            }

            return $objReturn;
        }

    }

    /**
     * Pega GeoLocation através de um IP
     *
     * @param String $ip
     */
    protected function getCountryGelocationByIP($ip)
    {
        try {

            $url = $this->drmUrl . "/services/GeoCheck?IPAddress=" . $ip;

            $response = Request::get($url)
                ->addHeaders(array(
                    'MAN-user-id' => 'app@sambatech.com',
                    'MAN-user-password' => 'c5kU6DCTmomi9fU',
                ))
                ->send();

            $responseXML = new \SimpleXMLElement($response);

            return $responseXML->attributes()['CountryCode'];

        } catch (\Exception $ex) {
            throw new \Exception("Erro ao tentar processar GeoLocation na IRDETO: ".$ex->getMessage());
        }
    }

    public function checkAuth(Video $video, $idprojeto, $compra)
    {
        $globalDisableDRM = DB::table('CFG_SYS')->where('data','disable_drm_global')->where('status','on')->first();

        // Projeto HML e Geolocation Brasil sempre autoriza DRM
        if($globalDisableDRM && ((int)$idprojeto !== 6135 && (int)$idprojeto !== 6548)){
            return true;
        }

        $shouldCheckGeolocation = \Cache::remember('should_check_geolocation',2,function(){
            return DB::table('CFG_SYS')->select('status')->where('data', 'internal_geolocation_lock')->where('status', 'on')->first();
        });


        /**
         * TRAVA DE GEOLOCATION
         */
        if($shouldCheckGeolocation && (int)$idprojeto !== 4307 && (int)$idprojeto !== 4872){
            try{
                $country = $this->getCountryGelocationByIP(AccessControlService::getClientIP());
                if($country != "BR"){
                    throw new RegionBlockedException("Este conteúdo não está disponível na sua região");
                }
            } catch(\Exception $ex) {
                \Log::error("Falha ao processar GEOLOCATION na IRDETO", [
                        'message' => $ex->getMessage()]
                );
            }
        }

        $sessionId = $this->getCurrentSessionId();

        // encontra policies de acordo com tipo de compra, tipo do projeto, duração do aluguel ou compra.
        $arrPolicyId = $this->getPoliciesForItem($video, $compra);

        $authCount = 0; // Contagem de autorizações bem sucedidas.
        $authTries = count($arrPolicyId); //O número máximo de tentativas.
        $exceptionCount = 0;

        try {
            if (count($arrPolicyId) > 0) {
                // Para cada policy encontrada, solicita autorização.
                foreach ($arrPolicyId as $policyId) {
                    $tvod = true;
                    if (!$compra instanceof BuyRent) {
                        $tvod = false;
                    }
                    $authTries--; // Vamos incrementar para saber se foi autorizado em pelo menos uma policy com sucesso.
                    $this->authorize($video->id_sambavideos, $sessionId, $policyId, $tvod);
                    $authCount++;
                }

            } else {
                // Não encontrou policy. fazer \Log do erro.
            }
        } catch (\Exception $ex) {
            if ($authTries === 0 && $authCount === 0) {
                // Gastou todas as tentativas e não conseguiu autorizar user?
                // Shame.
                throw $ex;
            } else {
                $exceptionCount++; //Incrementa numero de exceptions.
            }
            if ($exceptionCount === count($arrPolicyId)) {
                // O número de exceções é  igual ao numero de policies do conteúdo?
                // Shame.

                throw $ex;
            }
        }

        $authorization = $this->checkSessionAuth($video->id_sambavideos,$sessionId);

        // Se usuário ainda não tiver autorização, joga exception
        if($authorization->status === true){
            return true;
        } else{
            if($authorization->error === 100){
                throw new RegionBlockedException("Este conteúdo não está disponível na sua região");
            } else{
                throw new \Exception("User not authorized");
            }
        }
    }

    /**
     * @param $video Video
     * @param $compra object
     * @return int[]
     * @throws \Exception "Data de expiração inválida"
     */
    protected function getPoliciesForItem(Video $video, $compra)
    {
        // Encontra todas as policies
        $policies = collect($this->getPolicies());

        // Geolocation default é brasil
        $geolocation = null;

        // Projeto Sony, e Homologação Geolocation só deve visualizar conteúdo do brasil
        // O drm só encontrará uma licensa svod para brasil, e caso a autorização falhe, o player não será renderizado
        if ($video->id_project === 6145 || $video->id_project === 6135 || $video->id_project === 6548) {
            $geolocation = 'brasil';
        }

        if (!$compra instanceof BuyRent) {
            // Se $compra não for um buyrent, quer dizer que o tipo de autorização usada é svod.
            $arrPolicies = $policies->where('rental_type', 'svod');

            return $arrPolicies->pluck('policy_id')->toArray();
        }

        $rental_type = $compra->rental_type;
        if ($compra->rental_type === 'buy') {
            $rental_type = 'rent';
            $duration = '365';
        } else {
            //Precisaremos receber a data de expiração do aluguel no momento da transação.
            $expiration_date = $compra->getExpirationDate(false, true);

            if ($expiration_date < 0) {
                // Venda expirada. throw exception e redirecionar?
                \Log::error("Data de expiração invalida para DRM Authorize", [
                    'compra' => json_encode($compra)
                ]);
                throw new \Exception("Data de expiração inválida");
            }

            if ($expiration_date <= 2) {
                $duration = 2;
            } else {
                $duration = 30;
            }
        }

        $policies = $policies
            ->where('rental_type', $rental_type)
            ->where('duration', $duration);

        if ($geolocation) {
            $policies->where('geolocation', $geolocation);
        }

        $policies = $policies->pluck('policy_id')
            ->toArray();

        return $policies;

    }

    /**
     * Carrega policies DRM baseado no environment atual
     * @return mixed
     */
    protected function getPolicies()
    {
        $environment = app()->environment() === 'staging' ? 'staging' : 'production';
        return \Config::get('drm_policies.' . $environment . '.policies');
    }

    /**
     * Retorna a URL do método authorize que deve ser usada com os parametros preenchidos.
     *
     * @param $idSambaVdeos
     * @param $policyId int
     * @param $sessionId string
     * @param $authorizeAsTvod boolean
     * @return string
     */
    protected function getAuthorizeUrl($idSambaVideos, $policyId, $sessionId, $authorizeAsTvod = false)
    {
        $url = $this->drmUrl . "services/Authorize?CrmId=sambatech&SessionId=" . $sessionId . "&AccountId=sambatech";
        if ($authorizeAsTvod) {
            $url .= "&ContentId=" . $idSambaVideos . '&OptionId=' . $policyId . '&AccountId=sambatech';
        } else {
            $url .= '&PackageId=' . $policyId;
        }
        return $url;
    }

    /**
     * Grava um erro de DRM no banco de dados.
     * Verifica IP e geolocation do usuário.
     *
     * @param iMediaInterface $media
     * @param mixed[] $arr
     */
    public function reportError($arr)
    {
        $date = Carbon::now();
        $arr['created_at'] = $date;
        $arr['updated_at'] = $date;
        $arr['user_ip'] = AccessControlService::getClientIP();
        $arr['header'] = null;

        try {
            $arr['user_geolocation'] = json_encode(\GeoIP::getLocation());
        } catch (\Exception $ex) {
            $arr['user_geolocation'] = $ex->getMessage();
        }
        try {
            $arr['was_authorized'] = $this->checkSessionAuth($arr['id_sambavideos'], $this->getCurrentSessionId())->status;
        } catch (\Exception $ex) {
            $arr['was_authorized'] = $ex->getMessage();
        }

        if((int)$arr['error_code'] === 15){
            // Erros de DRM são ID 15.. vamos gravar mais dados do REFERRER para debug
            if(isset($_SERVER['HTTP_REFERER'])){
                $referer =$_SERVER['HTTP_REFERER'];
            } else{
                $referer = null;
            }

            if(isset($_SERVER['HTTP_USER_AGENT'])){
                $userAgent = $_SERVER['HTTP_USER_AGENT'];
            } else{
                $userAgent = null;
            }

            $arr['header'] = 'Referer: '.$referer. ' User Agent: '.$userAgent;
        }
        DB::table('drm_auth_errors')->insert($arr);
    }

}
