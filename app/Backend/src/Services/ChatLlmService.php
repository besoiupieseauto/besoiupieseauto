<?php



declare(strict_types=1);



namespace Besoiu\Services;



/**

 * LLM pentru chat public (widget) — cascadă Metro via LlmRouterService.

 */

final class ChatLlmService

{

    private LlmRouterService $router;



    public function __construct(

        private readonly string $projectRoot,

        ?LlmRouterService $router = null,

    ) {

        LlmClientSupport::loadEnv($projectRoot);

        $this->router = $router ?? LlmRouterService::create($projectRoot);

    }



    public static function create(string $projectRoot): self

    {

        return new self($projectRoot);

    }



    /** @return array{ready:bool,router:array<string,mixed>} */

    public function readiness(): array

    {

        return [

            'ready' => $this->router->isConfigured(),

            'router' => $this->router->readiness(),

        ];

    }



    /**

     * @param list<array{role:string,content:string}> $messages

     * @return array{ok:bool,content?:string,error?:string,provider?:string,model?:string,routed_via?:string}

     */

    public function chat(

        array $messages,

        string $systemPrompt = '',

        float $temperature = 0.7,

        int $timeoutSec = 60,

        string $usageSource = 'chat-widget',

    ): array {

        $routerResult = $this->router->chat(

            $messages,

            $systemPrompt,

            $temperature,

            $timeoutSec,

            'chat_widget'

        );



        if (!empty($routerResult['ok']) && trim((string) ($routerResult['content'] ?? '')) !== '') {

            return $this->normalize($routerResult);

        }



        return [

            'ok' => false,

            'error' => (string) ($routerResult['error'] ?? 'Niciun provider LLM disponibil pentru chat.'),

            'routed_via' => (string) ($routerResult['routed_via'] ?? 'none'),

        ];

    }



    /** @param array<string, mixed> $result @return array<string, mixed> */

    private function normalize(array $result): array

    {

        $via = (string) ($result['routed_via'] ?? $result['provider'] ?? 'llm');



        return [

            'ok' => true,

            'content' => trim((string) ($result['content'] ?? '')),

            'provider' => $via,

            'model' => (string) ($result['model'] ?? ''),

            'routed_via' => $via,

        ];

    }

}

