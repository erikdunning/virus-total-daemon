<?php 

namespace VirusTotal;

use PhpEws\EwsConnection;
use PhpEws\DataType\GetItemType;
use PhpEws\DataType\GetAttachmentType;
use PhpEws\DataType\RequestAttachmentIdType;
use PhpEws\DataType\FindItemType;
use PhpEws\DataType\ItemIdType;
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

class Reader {

    protected $ews;

    public function __construct(){
        $c = json_decode( file_get_contents( __DIR__ . '/../config.json' )  ); 
        $this->ews = new EwsConnection($c->exchange->host, $c->exchange->username, $c->exchange->password, EwsConnection::VERSION_2010_SP2);
    }

    public function getMessages(){

        $request = new FindItemType();

        $request->ItemShape = new ItemResponseShapeType();
        $request->ItemShape->BaseShape = DefaultShapeNamesType::DEFAULT_PROPERTIES;

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

        $filteredMessages = array();
        foreach( $messages as $m ){
            if( $m->HasAttachments == 1 ){
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

            if(!empty($message->Attachments->FileAttachment)) {
                // FileAttachment attribute can either be an array or instance of stdClass...
                $attachments = array();
                if(is_array($message->Attachments->FileAttachment) === FALSE ) {
                    $attachments[] = $message->Attachments->FileAttachment;
                }
                else {
                    $attachments = $message->Attachments->FileAttachment;
                }

                foreach($attachments as $attachment) {
                    $request = new GetAttachmentType();
                    $request->AttachmentIds = new NonEmptyArrayOfRequestAttachmentIdsType();
                    $request->AttachmentIds->AttachmentId = new RequestAttachmentIdType();
                    $request->AttachmentIds->AttachmentId->Id = $attachment->AttachmentId->Id;
                    $response = $this->ews->GetAttachment($request);

                    // Assuming response was successful ...
                    $attachments = $response->ResponseMessages->GetAttachmentResponseMessage->Attachments;
                    $content = $attachments->FileAttachment->Content;

                    $id = sha1( $attachment->AttachmentId->Id );
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


