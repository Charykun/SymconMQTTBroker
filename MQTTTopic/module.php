<?php

class MQTTopic extends IPSModule
{
    /**
     * Create
     */
    public function Create()
    {
        //Never delete this line!
        parent::Create();

        $this->RegisterPropertyString("Topic", "");
        $this->RegisterPropertyInteger("DataType", 3);
        $this->ConnectParent("{DE676D56-3FC1-4DD8-B65F-7116F48F03CE}");
    }

    /**
     * ApplyChanges
     */
    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();
        switch ($this->ReadPropertyInteger("DataType"))
        {
            case 0: $this->RegisterVariableBoolean("Value", "Value", "~Switch"); break;
            case 1: $this->RegisterVariableInteger("Value", "Value"); break;
            case 2: $this->RegisterVariableFloat("Value", "Value"); break;
            case 3: $this->RegisterVariableString("Value", "Value"); break;
        }
        $this->EnableAction("Value");
    }

    /**
     * ReceiveData
     * @param $JSONString
     */
    public function ReceiveData($JSONString)
    {
        $data = json_decode($JSONString);
        if ($data->DataID == "{60502747-993B-492A-BAD0-C61F07CEFADB}") {
            $topic = utf8_decode($data->Topic);
            $msg = utf8_decode($data->Msg);
            $this->SendDebug("Topic", $topic, 0);
            $this->SendDebug("Value", $msg, 0);
            if($this->ReadPropertyString("Topic") == $topic)
            {
                if(GetValue($this->GetIDForIdent("Value")) != $msg)
                {
                    SetValue($this->GetIDForIdent("Value"), $msg);
                }
            }
        }
    }

    /**
     * RequestAction
     * @param string $Ident
     * @param $Value
     */
    public function RequestAction($Ident, $Value)
    {
        switch ($Ident)
        {
            case "Value":
                $this->Publish($Value);
                break;
        }
    }

    /**
     * MQTTTopic_Publish()
     * @param $content
     * @return boolean
     */
    public function Publish($content)
    {
        $qos = 1;
        $retrain = 0;
        $topic = $this->ReadPropertyString("Topic");
        $i = 0;
        do
        {
            $this->SendDataToParent(json_encode(Array("DataID" => "{3EE22410-A758-41D9-95CE-AEF3D293FC5D}", "Topic" => $topic, "Content" => $content, "QOS" => $qos, "Retrain" => $retrain)));
            IPS_Sleep(1000);
            $i++;
            if($i == 15) return false;
        }
        while(GetValue($this->GetIDForIdent("Value")) != $content);
        return true;
    }
}