<?php
class ExactTargetHelper {

    public $ETURL = "http://exacttarget.com/wsdl/partnerAPI";
    public $client,$objClient,$objOpt;
    public function __construct($mid)
    {
        $wsdl = 'https://webservice.exacttarget.com/etframework.wsdl';
        $client = new ExactTargetSoapClient($wsdl, array('trace'=>1));
        $client->username = Config::get( 'aetn.exacttarget.username' ); 
        $client->password = Config::get( 'aetn.exacttarget.password' );  
        $this->client = $client;  

        $objClient = new ExactTarget_ClientID();
        $objClient->ID = $mid; // the account ID of the subaccount
        $this->objClient = $objClient;

        $objOpt = new ExactTarget_CreateOptions();
        $objOpt->Client = $mid; 
        $this->objOpt = $objOpt;
        
    } 
    public function retrieveTemplate($template_name)
    {
        /////
        ///// retrieve Template
        /////
        $rr = new ExactTarget_RetrieveRequest();
        $rr->ObjectType = 'Template';
        $rr->ClientIDs = $this->objClient;
        //Set the properties to return
        $props =  array('LayoutHTML');
        $rr->Properties = $props;

        //Setup account filtering, to look for a given account MID
        $filterPart = new ExactTarget_SimpleFilterPart();
        $filterPart->Property = 'TemplateName';
        $values = array($template_name);
        $filterPart->Value = $values;
        $filterPart->SimpleOperator = ExactTarget_SimpleOperators::equals;

        $filterPart = new SoapVar($filterPart, SOAP_ENC_OBJECT, 'SimpleFilterPart', $this->ETURL);
        $rr->Filter = $filterPart;

        //Setup and execute request
        $rrm = new ExactTarget_RetrieveRequestMsg();
        $rrm->RetrieveRequest = $rr;
        $template_results = $this->client->Retrieve($rrm);
        return $template_results;
    }
    public function createEmail($email)
    {

        $email_object = new SoapVar($email, SOAP_ENC_OBJECT, 'Email', $this->ETURL);
            
        $request = new ExactTarget_CreateRequest();
        $request->Options = $this->objOpt;
        $request->Objects = array($email_object);

        $email_results = $this->client->Create($request);
        return $email_results;
    }
    public function sendEmail($list_id, $email_id, $send_classification_id){
        /////
        ///// create sendDefinition
        ///// 

        $list = new ExactTarget_List();
        $list->ID = $list_id; // sending email to sue.park@aetn.com only <- TODO
           
        $senddeflist = new ExactTarget_SendDefinitionList();
        $senddeflist->List = $list;
        $senddeflist->DataSourceTypeID = "List"; // in this example, we are sending to a list

        $email = new ExactTarget_Email();
        $email->ID = $email_id;
            
        $sc = new ExactTarget_SendClassification();
        $sc->Client = $this->objClient;
        $sc->CustomerKey = $send_classification_id;

        $esd = new ExactTarget_EmailSendDefinition();
        $esd->Client = $this->objClient;
        $esd->SendDefinitionList = $senddeflist;
        $esd->Email = $email;
        $esd->Name = "API Created";
        $esd->SendClassification = $sc;
        $send_def_object = new SoapVar($esd, SOAP_ENC_OBJECT, 'EmailSendDefinition', $this->ETURL);   

        $request = new ExactTarget_CreateRequest();
        $request->Options = $this->objOpt;
        $request->Objects = array($send_def_object);
        $send_def_create_results = $this->client->Create($request);  
        var_dump($send_def_create_results);   
        if($send_def_create_results->Results->StatusCode == 'Error'){
            $send_def_create_statusMessage = $send_def_create_results->Results->StatusMessage;
            die ("$send_def_create_statusMessage\n");
        }
        /////
        ///// perform sendDefinition
        ///// 
        $new_esd = new ExactTarget_EmailSendDefinition();
        $new_esd->Client = $this->objClient;
        $new_esd->ObjectID = $send_def_create_results->Results->NewObjectID;

        $new_send_def_obj = new SoapVar($new_esd, SOAP_ENC_OBJECT, 'EmailSendDefinition', $this->ETURL);

        $pr = new ExactTarget_PerformRequestMsg();
        $pr->Action = "start";   
        $pr->Definitions[] = $new_send_def_obj ;
        $pr->Options = NULL;

        $send_def_perform_results = $this->client->Perform($pr);  
        var_dump($send_def_perform_results);
        if($send_def_perform_results->Results->StatusCode == 'Error'){
            $send_def_perform_statusMessage = $send_def_perform_results->Results->StatusMessage;
            die ("$send_def_perform_statusMessage\n");
        }
        /////
        ///// delete sendDefinition
        ///// 
        $request = new ExactTarget_DeleteRequest();
        $request->Options = $this->objOpt;
        $request->Objects = array($new_send_def_obj);
        $send_def_del_results = $this->client->Delete($request);
        var_dump($send_def_del_results);
        if($send_def_del_results->Results->StatusCode == 'Error'){
           $send_def_del_statusMessage = $send_def_del_results->Results->StatusMessage;
           die ("$send_def_del_statusMessage\n"); 
        }        
    }
    
}