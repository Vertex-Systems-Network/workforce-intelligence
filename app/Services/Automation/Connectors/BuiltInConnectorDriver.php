<?php
namespace App\Services\Automation\Connectors;

use App\Services\Automation\Contracts\ConnectorDriver;
use App\Services\Security\OutboundUrlGuard;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Provides built in connector driver behavior within the WorkIntel application. */ class BuiltInConnectorDriver implements ConnectorDriver
{
    /** Initializes the class with its required dependencies and state. */ public function __construct(private readonly string $provider, private readonly OutboundUrlGuard $guard) {}
    /** Handles the id operation for the current WorkIntel workflow. */ public function id(): string { return $this->provider; }

    /** Handles the catalog operation for the current WorkIntel workflow. */ public function catalog(): array
    {
        return self::definitions()[$this->provider] ?? throw new \InvalidArgumentException('Unknown connector provider.');
    }

    /** Handles the definitions operation for the current WorkIntel workflow. */ public static function definitions(): array
    {
        return [
            'slack'=>['id'=>'slack','name'=>'Slack','category'=>'communication','description'=>'Send messages through a Slack incoming webhook.','auth'=>'webhook','config_fields'=>[['key'=>'webhook_url','label'=>'Webhook URL','type'=>'secret','required'=>true]],'actions'=>[['key'=>'message.send','name'=>'Send message','fields'=>['text','blocks']]]],
            'teams'=>['id'=>'teams','name'=>'Microsoft Teams','category'=>'communication','description'=>'Send cards/messages to a Teams workflow or incoming webhook URL.','auth'=>'webhook','config_fields'=>[['key'=>'webhook_url','label'=>'Workflow / Webhook URL','type'=>'secret','required'=>true]],'actions'=>[['key'=>'message.send','name'=>'Send message','fields'=>['text','title']]]],
            'google_workspace'=>['id'=>'google_workspace','name'=>'Google Workspace','category'=>'productivity','description'=>'Gmail and Google Calendar actions using an OAuth access token supplied by your organization.','auth'=>'oauth_access_token','config_fields'=>[['key'=>'access_token','label'=>'OAuth Access Token','type'=>'secret','required'=>true],['key'=>'calendar_id','label'=>'Calendar ID','type'=>'text','required'=>false]],'actions'=>[['key'=>'gmail.send','name'=>'Send email','fields'=>['to','subject','body']],['key'=>'calendar.event','name'=>'Create calendar event','fields'=>['summary','start','end','description']]]],
            'microsoft365'=>['id'=>'microsoft365','name'=>'Microsoft 365','category'=>'productivity','description'=>'Microsoft Graph mail and calendar actions with an organization-managed OAuth token.','auth'=>'oauth_access_token','config_fields'=>[['key'=>'access_token','label'=>'OAuth Access Token','type'=>'secret','required'=>true],['key'=>'user_id','label'=>'Graph User ID / UPN','type'=>'text','required'=>false]],'actions'=>[['key'=>'mail.send','name'=>'Send email','fields'=>['to','subject','body']],['key'=>'calendar.event','name'=>'Create calendar event','fields'=>['subject','start','end','body']]]],
            'jira'=>['id'=>'jira','name'=>'Jira','category'=>'project_management','description'=>'Create Jira issues and comments.','auth'=>'api_token','config_fields'=>[['key'=>'base_url','label'=>'Jira Base URL','type'=>'url','required'=>true],['key'=>'email','label'=>'Email','type'=>'text','required'=>true],['key'=>'api_token','label'=>'API Token','type'=>'secret','required'=>true],['key'=>'project_key','label'=>'Project Key','type'=>'text','required'=>true]],'actions'=>[['key'=>'issue.create','name'=>'Create issue','fields'=>['summary','description','issue_type']],['key'=>'comment.create','name'=>'Add comment','fields'=>['issue_key','body']]]],
            'github'=>['id'=>'github','name'=>'GitHub','category'=>'development','description'=>'Create GitHub issues and comments.','auth'=>'token','config_fields'=>[['key'=>'token','label'=>'Fine-grained / PAT Token','type'=>'secret','required'=>true],['key'=>'repository','label'=>'Repository (owner/repo)','type'=>'text','required'=>true]],'actions'=>[['key'=>'issue.create','name'=>'Create issue','fields'=>['title','body','labels']],['key'=>'issue.comment','name'=>'Comment on issue','fields'=>['issue_number','body']]]],
            'gitlab'=>['id'=>'gitlab','name'=>'GitLab','category'=>'development','description'=>'Create GitLab issues through a personal/project access token.','auth'=>'token','config_fields'=>[['key'=>'base_url','label'=>'GitLab URL','type'=>'url','required'=>false],['key'=>'token','label'=>'Access Token','type'=>'secret','required'=>true],['key'=>'project_id','label'=>'Project ID / URL-encoded path','type'=>'text','required'=>true]],'actions'=>[['key'=>'issue.create','name'=>'Create issue','fields'=>['title','description','labels']]]],
            'clickup'=>['id'=>'clickup','name'=>'ClickUp','category'=>'project_management','description'=>'Create ClickUp tasks in a configured list.','auth'=>'token','config_fields'=>[['key'=>'token','label'=>'API Token','type'=>'secret','required'=>true],['key'=>'list_id','label'=>'List ID','type'=>'text','required'=>true]],'actions'=>[['key'=>'task.create','name'=>'Create task','fields'=>['name','description','priority','due_date']]]],
            'asana'=>['id'=>'asana','name'=>'Asana','category'=>'project_management','description'=>'Create Asana tasks and add them to a project.','auth'=>'token','config_fields'=>[['key'=>'token','label'=>'Access Token','type'=>'secret','required'=>true],['key'=>'project_gid','label'=>'Project GID','type'=>'text','required'=>true]],'actions'=>[['key'=>'task.create','name'=>'Create task','fields'=>['name','notes','due_on']]]],
            'monday'=>['id'=>'monday','name'=>'Monday.com','category'=>'project_management','description'=>'Create items on a Monday.com board using GraphQL.','auth'=>'token','config_fields'=>[['key'=>'token','label'=>'API Token','type'=>'secret','required'=>true],['key'=>'board_id','label'=>'Board ID','type'=>'text','required'=>true],['key'=>'group_id','label'=>'Group ID','type'=>'text','required'=>false]],'actions'=>[['key'=>'item.create','name'=>'Create item','fields'=>['item_name','column_values']]]],
            'quickbooks'=>['id'=>'quickbooks','name'=>'QuickBooks Online','category'=>'accounting','description'=>'Post prepared journal entries to QuickBooks Online. OAuth token refresh remains your connected-app responsibility.','auth'=>'oauth_access_token','config_fields'=>[['key'=>'access_token','label'=>'OAuth Access Token','type'=>'secret','required'=>true],['key'=>'realm_id','label'=>'Company / Realm ID','type'=>'text','required'=>true],['key'=>'environment','label'=>'Environment','type'=>'select','required'=>true,'options'=>['sandbox','production']]],'actions'=>[['key'=>'journal.create','name'=>'Create journal entry','fields'=>['journal']]]],
            'xero'=>['id'=>'xero','name'=>'Xero','category'=>'accounting','description'=>'Post prepared manual journals to Xero. OAuth token refresh remains your connected-app responsibility.','auth'=>'oauth_access_token','config_fields'=>[['key'=>'access_token','label'=>'OAuth Access Token','type'=>'secret','required'=>true],['key'=>'tenant_id','label'=>'Xero Tenant ID','type'=>'text','required'=>true]],'actions'=>[['key'=>'journal.create','name'=>'Create manual journal','fields'=>['journal']]]],
            'generic_webhook'=>['id'=>'generic_webhook','name'=>'Generic HTTP','category'=>'developer','description'=>'POST JSON to a validated HTTPS/HTTP endpoint.','auth'=>'custom','config_fields'=>[['key'=>'url','label'=>'Endpoint URL','type'=>'url','required'=>true],['key'=>'bearer_token','label'=>'Bearer Token','type'=>'secret','required'=>false]],'actions'=>[['key'=>'http.post','name'=>'POST JSON','fields'=>['body','headers']]]],
        ];
    }

    /** Validates validate config input before it is processed. */ public function validateConfig(array $config): array
    {
        $definition=$this->catalog(); $clean=[];
        foreach($definition['config_fields'] as $field){
            $key=$field['key']; $value=$config[$key]??null;
            if(($field['required']??false) && ($value===null || trim((string)$value)==='')) throw ValidationException::withMessages(["config.$key"=>["{$field['label']} is required."]]);
            if($value!==null && $value!=='') $clean[$key]=is_string($value)?trim($value):$value;
        }
        if($this->provider==='generic_webhook') $this->guard->assertSafe((string)($clean['url']??''));
        if(in_array($this->provider,['slack','teams'],true)) $this->guard->assertSafe((string)($clean['webhook_url']??''));
        if($this->provider==='jira') $this->guard->assertSafe((string)($clean['base_url']??''));
        if($this->provider==='gitlab' && !empty($clean['base_url'])) $this->guard->assertSafe((string)$clean['base_url']);
        if($this->provider==='github' && !preg_match('#^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$#',(string)($clean['repository']??''))) throw ValidationException::withMessages(['config.repository'=>['Use owner/repository format.']]);
        if($this->provider==='quickbooks' && !in_array($clean['environment']??'production',['sandbox','production'],true)) throw ValidationException::withMessages(['config.environment'=>['Choose sandbox or production.']]);
        return $clean;
    }

    /** Handles the test operation for the current WorkIntel workflow. */ public function test(array $config, int $timeoutSeconds = 8): array
    {
        $config=$this->validateConfig($config);
        return match($this->provider){
            'slack','teams'=>$this->execute('message.send',$config,['text'=>'WorkIntel connection test'], $timeoutSeconds),
            'google_workspace'=>$this->response(Http::timeout($timeoutSeconds)->withToken($config['access_token'])->get('https://www.googleapis.com/calendar/v3/users/me/calendarList',['maxResults'=>1])) ,
            'microsoft365'=>$this->response(Http::timeout($timeoutSeconds)->withToken($config['access_token'])->get('https://graph.microsoft.com/v1.0/me')),
            'jira'=>$this->response(Http::timeout($timeoutSeconds)->withBasicAuth($config['email'],$config['api_token'])->get(rtrim($config['base_url'],'/').'/rest/api/3/myself')),
            'github'=>$this->response(Http::timeout($timeoutSeconds)->withToken($config['token'])->withHeaders(['User-Agent'=>'WorkIntel-Automations'])->get('https://api.github.com/user')),
            'gitlab'=>$this->response(Http::timeout($timeoutSeconds)->withHeaders(['PRIVATE-TOKEN'=>$config['token']])->get(rtrim($config['base_url']??'https://gitlab.com','/').'/api/v4/user')),
            'clickup'=>$this->response(Http::timeout($timeoutSeconds)->withHeaders(['Authorization'=>$config['token']])->get('https://api.clickup.com/api/v2/user')),
            'asana'=>$this->response(Http::timeout($timeoutSeconds)->withToken($config['token'])->get('https://app.asana.com/api/1.0/users/me')),
            'monday'=>$this->response(Http::timeout($timeoutSeconds)->withHeaders(['Authorization'=>$config['token'],'API-Version'=>'2024-10'])->post('https://api.monday.com/v2',['query'=>'query { me { id name } }'])),
            'quickbooks'=>$this->response(Http::timeout($timeoutSeconds)->withToken($config['access_token'])->acceptJson()->get($this->quickBooksBase($config).'/v3/company/'.rawurlencode($config['realm_id']).'/companyinfo/'.rawurlencode($config['realm_id']),['minorversion'=>75])),
            'xero'=>$this->response(Http::timeout($timeoutSeconds)->withToken($config['access_token'])->get('https://api.xero.com/connections')),
            'generic_webhook'=>$this->execute('http.post',$config,['body'=>['type'=>'workintel.integration.test','created_at'=>now()->toIso8601String()]],$timeoutSeconds),
            default=>throw new \RuntimeException('Unsupported connector provider.'),
        };
    }

    /** Handles the execute operation for the current WorkIntel workflow. */ public function execute(string $actionKey, array $config, array $input, int $timeoutSeconds = 12): array
    {
        $config=$this->validateConfig($config);
        $allowed=collect($this->catalog()['actions'])->pluck('key')->all();
        if(!in_array($actionKey,$allowed,true)) throw ValidationException::withMessages(['action_key'=>["Action {$actionKey} is not supported by {$this->provider}."]]);
        return match($this->provider){
            'slack'=>$this->response(Http::timeout($timeoutSeconds)->post($config['webhook_url'],array_filter(['text'=>(string)($input['text']??''),'blocks'=>$input['blocks']??null],fn($v)=>$v!==null))),
            'teams'=>$this->response(Http::timeout($timeoutSeconds)->post($config['webhook_url'],['type'=>'message','attachments'=>[['contentType'=>'application/vnd.microsoft.card.adaptive','contentUrl'=>null,'content'=>['$schema'=>'http://adaptivecards.io/schemas/adaptive-card.json','type'=>'AdaptiveCard','version'=>'1.4','body'=>[['type'=>'TextBlock','text'=>(string)($input['title']??'WorkIntel'),'weight'=>'Bolder'],['type'=>'TextBlock','text'=>(string)($input['text']??''),'wrap'=>true]]]]]])),
            'google_workspace'=>$this->executeGoogle($actionKey,$config,$input,$timeoutSeconds),
            'microsoft365'=>$this->executeMicrosoft365($actionKey,$config,$input,$timeoutSeconds),
            'jira'=>$this->executeJira($actionKey,$config,$input,$timeoutSeconds),
            'github'=>$this->executeGitHub($actionKey,$config,$input,$timeoutSeconds),
            'gitlab'=>$this->response(Http::timeout($timeoutSeconds)->withHeaders(['PRIVATE-TOKEN'=>$config['token']])->post(rtrim($config['base_url']??'https://gitlab.com','/').'/api/v4/projects/'.rawurlencode($config['project_id']).'/issues',['title'=>$input['title']??'WorkIntel item','description'=>$input['description']??null,'labels'=>is_array($input['labels']??null)?implode(',',$input['labels']):($input['labels']??null)])),
            'clickup'=>$this->response(Http::timeout($timeoutSeconds)->withHeaders(['Authorization'=>$config['token']])->post('https://api.clickup.com/api/v2/list/'.rawurlencode($config['list_id']).'/task',array_filter(['name'=>$input['name']??'WorkIntel task','description'=>$input['description']??null,'priority'=>$input['priority']??null,'due_date'=>$input['due_date']??null],fn($v)=>$v!==null))),
            'asana'=>$this->response(Http::timeout($timeoutSeconds)->withToken($config['token'])->post('https://app.asana.com/api/1.0/tasks',['data'=>array_filter(['name'=>$input['name']??'WorkIntel task','notes'=>$input['notes']??null,'due_on'=>$input['due_on']??null,'projects'=>[$config['project_gid']]],fn($v)=>$v!==null)])),
            'monday'=>$this->executeMonday($config,$input,$timeoutSeconds),
            'quickbooks'=>$this->response(Http::timeout($timeoutSeconds)->withToken($config['access_token'])->acceptJson()->post($this->quickBooksBase($config).'/v3/company/'.rawurlencode($config['realm_id']).'/journalentry?minorversion=75',$input['journal']??[])),
            'xero'=>$this->response(Http::timeout($timeoutSeconds)->withToken($config['access_token'])->withHeaders(['xero-tenant-id'=>$config['tenant_id']])->post('https://api.xero.com/api.xro/2.0/ManualJournals',['ManualJournals'=>[$input['journal']??[]]])),
            'generic_webhook'=>$this->response(Http::timeout($timeoutSeconds)->withHeaders(array_merge(is_array($input['headers']??null)?$input['headers']:[],!empty($config['bearer_token'])?['Authorization'=>'Bearer '.$config['bearer_token']]:[]))->post($config['url'],$this->jsonBody($input['body']??$input))),
            default=>throw new \RuntimeException('Unsupported connector provider.'),
        };
    }

    /** Handles the execute google operation for the current WorkIntel workflow. */ private function executeGoogle(string $action,array $config,array $input,int $timeout): array
    {
        $http=Http::timeout($timeout)->withToken($config['access_token'])->acceptJson();
        if($action==='gmail.send'){
            $to=(string)($input['to']??'');$subject=(string)($input['subject']??'');$body=(string)($input['body']??'');
            $mime="To: {$to}\r\nSubject: {$subject}\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\n{$body}";
            $raw=rtrim(strtr(base64_encode($mime),'+/','-_'),'=');
            return $this->response($http->post('https://gmail.googleapis.com/gmail/v1/users/me/messages/send',['raw'=>$raw]));
        }
        $calendar=rawurlencode($config['calendar_id']??'primary');
        return $this->response($http->post("https://www.googleapis.com/calendar/v3/calendars/{$calendar}/events",array_filter(['summary'=>$input['summary']??'WorkIntel event','description'=>$input['description']??null,'start'=>['dateTime'=>$input['start']??now()->toIso8601String()],'end'=>['dateTime'=>$input['end']??now()->addHour()->toIso8601String()]],fn($v)=>$v!==null)));
    }

    /** Handles the execute microsoft365 operation for the current WorkIntel workflow. */ private function executeMicrosoft365(string $action,array $config,array $input,int $timeout): array
    {
        $user=rawurlencode($config['user_id']??'me');$base='https://graph.microsoft.com/v1.0/'.($user==='me'?'me':'users/'.$user);$http=Http::timeout($timeout)->withToken($config['access_token'])->acceptJson();
        if($action==='mail.send') return $this->response($http->post($base.'/sendMail',['message'=>['subject'=>$input['subject']??'WorkIntel notification','body'=>['contentType'=>'Text','content'=>$input['body']??''],'toRecipients'=>[['emailAddress'=>['address'=>$input['to']??'']]]]]));
        return $this->response($http->post($base.'/events',['subject'=>$input['subject']??'WorkIntel event','body'=>['contentType'=>'Text','content'=>$input['body']??''],'start'=>['dateTime'=>$input['start']??now()->toIso8601String(),'timeZone'=>'UTC'],'end'=>['dateTime'=>$input['end']??now()->addHour()->toIso8601String(),'timeZone'=>'UTC']]));
    }

    /** Handles the execute jira operation for the current WorkIntel workflow. */ private function executeJira(string $action,array $config,array $input,int $timeout): array
    {
        $http=Http::timeout($timeout)->withBasicAuth($config['email'],$config['api_token'])->acceptJson();$base=rtrim($config['base_url'],'/').'/rest/api/3';
        if($action==='comment.create') return $this->response($http->post($base.'/issue/'.rawurlencode($input['issue_key']??'').'/comment',['body'=>$this->jiraDoc((string)($input['body']??''))]));
        return $this->response($http->post($base.'/issue',['fields'=>['project'=>['key'=>$config['project_key']],'summary'=>$input['summary']??'WorkIntel item','description'=>$this->jiraDoc((string)($input['description']??'')),'issuetype'=>['name'=>$input['issue_type']??'Task']]]));
    }

    /** Handles the execute git hub operation for the current WorkIntel workflow. */ private function executeGitHub(string $action,array $config,array $input,int $timeout): array
    {
        $base='https://api.github.com/repos/'.$config['repository'];$http=Http::timeout($timeout)->withToken($config['token'])->withHeaders(['User-Agent'=>'WorkIntel-Automations'])->acceptJson();
        if($action==='issue.comment') return $this->response($http->post($base.'/issues/'.(int)($input['issue_number']??0).'/comments',['body'=>$input['body']??'']));
        return $this->response($http->post($base.'/issues',array_filter(['title'=>$input['title']??'WorkIntel item','body'=>$input['body']??null,'labels'=>$input['labels']??null],fn($v)=>$v!==null)));
    }

    /** Handles the execute monday operation for the current WorkIntel workflow. */ private function executeMonday(array $config,array $input,int $timeout): array
    {
        $query='mutation ($board: ID!, $name: String!, $group: String, $columns: JSON) { create_item(board_id: $board, item_name: $name, group_id: $group, column_values: $columns) { id name } }';
        $columns=$input['column_values']??null;if(is_array($columns))$columns=json_encode($columns,JSON_UNESCAPED_SLASHES);
        return $this->response(Http::timeout($timeout)->withHeaders(['Authorization'=>$config['token'],'API-Version'=>'2024-10'])->post('https://api.monday.com/v2',['query'=>$query,'variables'=>['board'=>(string)$config['board_id'],'name'=>(string)($input['item_name']??'WorkIntel item'),'group'=>$config['group_id']??null,'columns'=>$columns]]));
    }

    /** Handles the json body operation for the current WorkIntel workflow. */ private function jsonBody(mixed $value): array { return is_array($value) ? $value : ['value' => $value]; }
    /** Handles the jira doc operation for the current WorkIntel workflow. */ private function jiraDoc(string $text): array { return ['type'=>'doc','version'=>1,'content'=>[['type'=>'paragraph','content'=>[['type'=>'text','text'=>$text?:' ']]]]]; }
    /** Handles the quick books base operation for the current WorkIntel workflow. */ private function quickBooksBase(array $config): string { return ($config['environment']??'production')==='sandbox'?'https://sandbox-quickbooks.api.intuit.com':'https://quickbooks.api.intuit.com'; }
    /** Handles the response operation for the current WorkIntel workflow. */ private function response(Response $response): array
    {
        if(!$response->successful()) throw new \RuntimeException('Connector request failed with HTTP '.$response->status().': '.Str::limit($response->body(),700,''));
        $json=$response->json(); return ['ok'=>true,'status'=>$response->status(),'data'=>is_array($json)?$json:['body'=>Str::limit($response->body(),1200,'')]];
    }
}
