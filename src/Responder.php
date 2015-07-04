<?php

namespace VirusTotal;

use Smarty;
use PhpEws\EwsConnection;
use PhpEws\Ntlm\NtlmSoapClient;
use PhpEws\Ntlm\NtlmSoapClient\Exchange;
use PhpEws\Exception\EwsException;
use PhpEws\DataType;

use PhpEws\DataType\MessageType;
use PhpEws\DataType\EmailAddressType;
use PhpEws\DataType\BodyType;
use PhpEws\DataType\SingleRecipientType;
use PhpEws\DataType\CreateItemType;
use PhpEws\DataType\ArrayOfRecipientsType;
use PhpEws\DataType\NonEmptyArrayOfAllItemsType;
use PhpEws\DataType\ItemType;
use PhpEws\DataType\ExchangeImpersonationType;
use PhpEws\DataType\ConnectingSIDType;
use PhpEws\DataType\FileAttachmentType;
use PhpEws\DataType\CreateAttachmentType;
use PhpEws\DataType\SendItemType;
use PhpEws\DataType\ItemIdType;
use PhpEws\DataType\TargetFolderIdType;
use PhpEws\DataType\DistinguishedFolderIdType;
use PhpEws\DataType\NonEmptyArrayOfAttachmentsType;

class Responder {

    protected $ews;
    protected $smarty;

    public function __construct(){
        $c = json_decode( file_get_contents( __DIR__ . '/../config.json' )  ); 
        $this->ews = new EwsConnection($c->exchange->host, $c->exchange->username, $c->exchange->password, EwsConnection::VERSION_2010_SP2);
        $this->smarty = new Smarty();
        $this->smarty->setTemplateDir(  __DIR__ . '/../smarty/templates'     );
        $this->smarty->setCompileDir(   __DIR__ . '/../smarty/templates_c'   );
        $this->smarty->setConfigDir(    __DIR__ . '/../smarty/configs'       );
        $this->smarty->setCacheDir(     __DIR__ . '/../smarty/cache'         );
    }

    public function sendResponse( $job, $report ){

        $parsedReport = json_decode( $report ); 

        $s = $this->smarty;
        $s->clearAllAssign();

        $s->assign('permalink',         $parsedReport->permalink    );
        $s->assign('filename',          $job->attachment_name       );
        $s->assign('time_added',        $job->time_added            );
        $s->assign('time_sent',         $job->time_sent             );
        $s->assign('time_completed',    $job->time_completed        );
    
        //$msg = new ReplyAllToItemType();
        $msg = new MessageType();

        /* In Case you need to add anyone in CC
        $cc = new ArrayOfRecipientsType();
        $cc->Mailbox = new EmailAddressType();
        $cc->Mailbox->EmailAddress = 'emailaddresshere';
        $cc->Mailbox->Name = 'displaynamehere';
        $msg->CcRecipients = $cc;
        */

        $msg->ReferenceItemId = new ItemIdType();
        $msg->ReferenceItemId->Id = $job->email_id;
        $msg->ReferenceItemId->ChangeKey = $job->email_change_key;

        $msg->NewBodyContent = new BodyType();
        $msg->NewBodyContent->BodyType = 'HTML';
        $msg->NewBodyContent->_ = $s->fetch('response.tmpl');

        $msgRequest = new CreateItemType();
        $msgRequest->Items = new NonEmptyArrayOfAllItemsType();
        $msgRequest->Items->ReplyAllToItem = $msg;
        $msgRequest->MessageDisposition = 'SendAndSaveCopy';
        $msgRequest->MessageDispositionSpecified = TRUE;

        $response = $this->ews->CreateItem($msgRequest);

        return $response->ResponseMessages->CreateItemResponseMessage->ResponseCode;

    }

}
