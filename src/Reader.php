<?php 

namespace VirusTotal;

use stdClass;
use PhpEws\EwsConnection;
use PhpEws\DataType\GetItemType;
use PhpEws\DataType\GetAttachmentType;
use PhpEws\DataType\RequestAttachmentIdType;
use PhpEws\DataType\FindItemType;
use PhpEws\DataType\ItemIdType;
use PhpEws\DataType\RestrictionType;
use PhpEws\DataType\ConstantValueType;
use PhpEws\DataType\FieldURIOrConstantType;
use PhpEws\DataType\IsGreaterThanOrEqualToType;
use PhpEws\DataType\ItemResponseShapeType;
use PhpEws\DataType\DefaultShapeNamesType;
use PhpEws\DataType\ItemQueryTraversalType;
use PhpEws\DataType\DistinguishedFolderIdType;
use PhpEws\DataType\DistinguishedFolderIdNameType;
use PhpEws\DataType\IndexedPageViewType;
use PhpEws\DataType\FieldOrderType;
use PhpEws\DataType\NonEmptyArrayOfFieldOrdersType;
use PhpEws\DataType\NonEmptyArrayOfBaseItemIdsType;
use PhpEws\DataType\NonEmptyArrayOfBaseFolderIdsType;
use PhpEws\DataType\NonEmptyArrayOfRequestAttachmentIdsType;

use VirusTotal\Data;

class Reader {

    protected $ews;
    protected $config;
    protected $data;

    public function __construct(){
        $this->config = json_decode( file_get_contents( __DIR__ . '/../config.json' )  ); 
        $c = $this->config;
        $this->ews = new EwsConnection($c->exchange->host, $c->exchange->username, $c->exchange->password, EwsConnection::VERSION_2010_SP2);
        $this->data = new Data();
    }

    public function getMessages( $start ){

        $request = new FindItemType();

        $request->ItemShape = new ItemResponseShapeType();
        $request->ItemShape->BaseShape = DefaultShapeNamesType::DEFAULT_PROPERTIES;

        $request->Restriction = new RestrictionType();
        $request->Restriction->IsGreaterThanOrEqualTo = new IsGreaterThanOrEqualToType();
        $request->Restriction->IsGreaterThanOrEqualTo->FieldURI = new stdClass;
        $request->Restriction->IsGreaterThanOrEqualTo->FieldURI->FieldURI = 'item:DateTimeReceived';
        $request->Restriction->IsGreaterThanOrEqualTo->FieldURIOrConstant = new FieldURIOrConstantType();
        $request->Restriction->IsGreaterThanOrEqualTo->FieldURIOrConstant->Constant = new ConstantValueType();
        $request->Restriction->IsGreaterThanOrEqualTo->FieldURIOrConstant->Constant->Value = $start->format('c');

        $request->Traversal = ItemQueryTraversalType::SHALLOW;

        $request->IndexedPageItemView = new IndexedPageViewType();
        $request->IndexedPageItemView->BasePoint = "Beginning";
        $request->IndexedPageItemView->Offset = 0;
        $request->IndexedPageItemView->MaxEntriesReturned = 100;

        $request->ParentFolderIds = new NonEmptyArrayOfBaseFolderIdsType();
        $request->ParentFolderIds->DistinguishedFolderId = new DistinguishedFolderIdType();
        $request->ParentFolderIds->DistinguishedFolderId->Id = DistinguishedFolderIdNameType::INBOX;

        // sort order
        $request->SortOrder = new NonEmptyArrayOfFieldOrdersType();
        $request->SortOrder->FieldOrder = array();
        $order = new FieldOrderType();

        // sorts mails so that oldest appear first
        // more field uri definitions can be found from types.xsd (look for UnindexedFieldURIType)
        @$order->FieldURI->FieldURI = 'item:DateTimeReceived'; 
        $order->Order = 'Ascending'; 
        $request->SortOrder->FieldOrder[] = $order;

        $response = $this->ews->FindItem($request);

        if(!isset($response->ResponseMessages->FindItemResponseMessage->RootFolder)){
            $responseMessage = $response->ResponseMessages->FindItemResponseMessage;
            die("\n" . $responseMessage->MessageText . "\n\n" . $responseMessage->ResponseCode . "\n");
        } else {
            $totalItems = $response->ResponseMessages->FindItemResponseMessage->RootFolder->TotalItemsInView;
        }

        $rootFolder = $response->ResponseMessages->FindItemResponseMessage->RootFolder; 

        if( ! isset( $rootFolder->Items->Message ) ){
            return array();
        }

        $messages = $rootFolder->Items->Message;
        $lastItemInRange = $rootFolder->IncludesLastItemInRange;
        $page = 1;

        while($lastItemInRange != 1)
        {
            $limit = $request->IndexedPageItemView->MaxEntriesReturned;
            $request->IndexedPageItemView->Offset = $limit * $page;

            $response = $this->ews->FindItem($request);

            $rootFolder = $response->ResponseMessages->FindItemResponseMessage->RootFolder;
            $messages = array_merge($messages, $rootFolder->Items->Message);
            $lastItemInRange = $rootFolder->IncludesLastItemInRange;

            $page++;
        }

        if( $messages && ( ! is_array( $messages ) ) ){
            $messages = array( $messages );
        }

        $filteredMessages = array();
        foreach( $messages as $m ){
            if( property_exists( $m, 'HasAttachments' ) && ( $m->HasAttachments == 1 ) ){
                $filteredMessages[] = $m;
            }
        }

        return $filteredMessages;
    }

    public function downloadAttachments( $message ){

        $c = json_decode( file_get_contents( __DIR__ . '/../config.json' )  ); 

        $message_id = $message->ItemId->Id;
        $save_dir = $c->attachments->directory;

        $request = new GetItemType();

        $request->ItemShape = new ItemResponseShapeType();
        $request->ItemShape->BaseShape = DefaultShapeNamesType::ALL_PROPERTIES;

        $request->ItemIds = new NonEmptyArrayOfBaseItemIdsType();
        $request->ItemIds->ItemId = new ItemIdType();
        $request->ItemIds->ItemId->Id = $message_id; 

        $response = $this->ews->GetItem($request);

        $attachmentItems = [];

        if( $response->ResponseMessages->GetItemResponseMessage->ResponseCode == 'NoError' &&
            $response->ResponseMessages->GetItemResponseMessage->ResponseClass == 'Success' ) {

            $message = $response->ResponseMessages->GetItemResponseMessage->Items->Message;

            // FileAttachment attribute can either be an array or instance of stdClass...
            $attachments = array();
            
            if( property_exists( $message->Attachments, 'FileAttachment' ) && !empty( $message->Attachments->FileAttachment ) ){
                if( is_array($message->Attachments->FileAttachment) === FALSE ) {
                    $attachments[] = $message->Attachments->FileAttachment;
                }
                else {
                    $attachments = $message->Attachments->FileAttachment;
                }
            }

            if( property_exists( $message->Attachments, 'ItemAttachment' ) && !empty( $message->Attachments->ItemAttachment ) ){ 
                if( is_array($message->Attachments->ItemAttachment) === FALSE ) {
                    $attachments[] = $message->Attachments->ItemAttachment;
                }
                else {
                    $attachments = array_merge( $attachments, $message->Attachments->ItemAttachment );
                }
            }

            if( sizeof($attachments) > 0 ) {

                foreach($attachments as $attachment) {

                    $id = sha1( $attachment->AttachmentId->Id );

                    if( $this->data->attachmentExists( $id ) ){
                        continue;
                    }

                    $request = new GetAttachmentType();
                    $request->AttachmentIds = new NonEmptyArrayOfRequestAttachmentIdsType();
                    $request->AttachmentIds->AttachmentId = new RequestAttachmentIdType();
                    $request->AttachmentIds->AttachmentId->Id = $attachment->AttachmentId->Id;
                    $response = $this->ews->GetAttachment($request);

                    // Assuming response was successful ...
                    $attachments = $response->ResponseMessages->GetAttachmentResponseMessage->Attachments;
                    $content = $attachments->FileAttachment->Content;

                    if( ! file_exists( $save_dir ) ){
                        error_log('Virus Total Daemon: Attachment directory does not exist! ' . $save_dir);
                        continue;
                    }

                    file_put_contents("$save_dir/$id", $content);
                    $attachmentItems[] = array(
                        'attachment_id' => $id,
                        'attachment_name' => $attachment->Name,
                        'attachment_size' => $attachment->Size,
                    );
                }
            }
            else {
                echo "No attachments found!\n";
            }   
        }

        return $attachmentItems;
    } 
}


