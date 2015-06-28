<?php

namespace VirusTotal;

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

    public function __construct(){
        $c = json_decode( file_get_contents( __DIR__ . '/../config.json' )  ); 
        $this->ews = new EwsConnection($c->exchange->host, $c->exchange->username, $c->exchange->password, EwsConnection::VERSION_2010_SP2);
    }

    public function replyToMessage($id,$changeKey){

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
        $msg->ReferenceItemId->Id = $id;
        $msg->ReferenceItemId->ChangeKey = $changeKey;

        $msg->NewBodyContent = new BodyType();
        $msg->NewBodyContent->BodyType = 'HTML';
        $msg->NewBodyContent->_ = 'HTML Content Goes Here';

        $msgRequest = new CreateItemType();
        $msgRequest->Items = new NonEmptyArrayOfAllItemsType();
        $msgRequest->Items->ReplyAllToItem = $msg;
        $msgRequest->MessageDisposition = 'SendAndSaveCopy';
        $msgRequest->MessageDispositionSpecified = TRUE;

        $response = $ews->CreateItem($msgRequest);

        return $response->ResponseMessages->CreateItemResponseMessage->ResponseCode;

    }

    protected function sendEmail( $toAddresses ){
        
        // Configure impersonation
        $ei = new ExchangeImpersonationType();
        $sid = new ConnectingSIDType();
        $sid->PrimarySmtpAddress = 'mark.sneed@domain.com';
        $ei->ConnectingSID = $sid;
        $this->ews->setImpersonation($ei);
        
        // Create message
        $msg = new MessageType();
        $toAddresses = array();
        $toAddresses[0] = new EmailAddressType();
        $toAddresses[0]->EmailAddress = 'sara.smith@domain.com';
        $toAddresses[0]->Name = 'Sara Smith';
        
        $msg->ToRecipients = $toAddresses;
        $msg->Subject = 'Test email message';
        
        $msg->Body = new BodyType();
        $msg->Body->BodyType = 'HTML';
        $msg->Body->_ = '<p style="font-size: 18px;"><b>Test email msg from php ews library</b></p>';
        
        // Save message
        $msgRequest = new CreateItemType();
        $msgRequest->Items = new NonEmptyArrayOfAllItemsType();
        $msgRequest->Items->Message = $msg;
        $msgRequest->MessageDisposition = 'SaveOnly';
        $msgRequest->MessageDispositionSpecified = true;
        
        $msgResponse = $this->ews->CreateItem($msgRequest);
        $msgResponseItems = $msgResponse->ResponseMessages->CreateItemResponseMessage->Items;
        
        // Create attachment(s)
        $attachments = array();
        $attachments[0] = new FileAttachmentType();
        $attachments[0]->Content = file_get_contents('path-to-file.pdf');
        $attachments[0]->Name = 'File Name.pdf';
        $attachments[0]->ContentType = 'application/pdf';
        
        // Attach files to message
        $attRequest = new CreateAttachmentType();
        $attRequest->ParentItemId = $msgResponseItems->Message->ItemId;
                $attRequest->Attachments = new NonEmptyArrayOfAttachmentsType();
        $attRequest->Attachments->FileAttachment = $attachments;
        
        $attResponse = $this->ews->CreateAttachment($attRequest);
        $attResponseId = $attResponse->ResponseMessages->CreateAttachmentResponseMessage->Attachments->FileAttachment->AttachmentId;
        
        // Save message id from create attachment response
        $msgItemId = new ItemIdType();
        $msgItemId->ChangeKey = $attResponseId->RootItemChangeKey;
        $msgItemId->Id = $attResponseId->RootItemId;
        
        // Send and save message
        $msgSendRequest = new SendItemType();
                $msgSendRequest->ItemIds = new NonEmptyArrayOfBaseItemIdsType();
        $msgSendRequest->ItemIds->ItemId = $msgItemId;
        $msgSendRequest->SavedItemFolderId = new TargetFolderIdType();
        $sentItemsFolder = new DistinguishedFolderIdType();
        $sentItemsFolder->Id = 'sentitems';
        $msgSendRequest->SavedItemFolderId->DistinguishedFolderId = $sentItemsFolder;
        $msgSendRequest->SaveItemToFolder = true;
        $msgSendResponse = $this->ews->SendItem($msgSendRequest);
        
        //var_dump($msgSendResponse);
    }

}
