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

use VirusTotal\Data;

class Responder {

    protected $ews;
    protected $smarty;
    protected $data;

    public function __construct(){
        $this->config = json_decode( file_get_contents( __DIR__ . '/../config.json' )  ); 
        $c = $this->config;
        $this->ews = new EwsConnection($c->exchange->host, $c->exchange->username, $c->exchange->password, EwsConnection::VERSION_2010_SP2);
        $this->smarty = new Smarty();
        $this->smarty->setTemplateDir(  __DIR__ . '/../smarty/templates'     );
        $this->smarty->setCompileDir(   __DIR__ . '/../smarty/templates_c'   );
        $this->smarty->setConfigDir(    __DIR__ . '/../smarty/configs'       );
        $this->smarty->setCacheDir(     __DIR__ . '/../smarty/cache'         );
        $this->data = new Data();
    }

    public function sendResponse( $job ){

        $s = $this->smarty;
        $s->clearAllAssign();

        $completedSet = $this->data->getCompletedSet( $job );
        if( $completedSet === FALSE ){
            return;
        }

        $positives = 0;
        $attachments = array();
        foreach( $completedSet as $job ){
            $parsedReport = json_decode( $job->report ); 
            $positives += intval( $parsedReport->positives );
            $attachments[] = array(
                'permalink'         =>  $parsedReport->permalink            ,
                'positives'         =>  $parsedReport->positives            ,
                'filename'          =>  $job->attachment_name               ,
                'time_added'        =>  date('r', $job->time_added )        ,
                'time_sent'         =>  date('r', $job->time_sent )         ,
                'time_completed'    =>  date('r', $job->time_completed )    
            );
        }


        $s->assign('positives', $positives);
        $s->assign('attachments', $attachments);
    
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

    public function sendSizeExceeded( $message, $sizeExceeded ){
        $s = $this->smarty;
        $s->clearAllAssign();

        $attachments = array();
        foreach( $sizeExceeded as $attachment ){
            $attachments[] = array(
                'filename'  =>  $attachment->Name,
                'size'      =>  $attachment->Size
            );
        }

        $s->assign('limit', number_format( ( doubleval( $this->config->attachments->maxSize ) / 1000000 ), 2 ));
        $s->assign('attachments', $attachments);
    
        $msg = new MessageType();

        $msg->ReferenceItemId = new ItemIdType();
        $msg->ReferenceItemId->Id = $message->ItemId->Id;
        $msg->ReferenceItemId->ChangeKey = $message->ItemId->ChangeKey;

        $msg->NewBodyContent = new BodyType();
        $msg->NewBodyContent->BodyType = 'HTML';
        $msg->NewBodyContent->_ = $s->fetch('size_exceeded.tmpl');

        $msgRequest = new CreateItemType();
        $msgRequest->Items = new NonEmptyArrayOfAllItemsType();
        $msgRequest->Items->ReplyAllToItem = $msg;
        $msgRequest->MessageDisposition = 'SendAndSaveCopy';
        $msgRequest->MessageDispositionSpecified = TRUE;

        $response = $this->ews->CreateItem($msgRequest);

        return $response->ResponseMessages->CreateItemResponseMessage->ResponseCode;
    }

}
