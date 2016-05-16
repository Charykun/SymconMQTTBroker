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
            case 0: $this->RegisterVariableBoolean("Value", "Value"); break;
            case 1: $this->RegisterVariableInteger("Value", "Value"); break;
            case 2: $this->RegisterVariableFloat("Value", "Value"); break;
            case 3: $this->RegisterVariableString("Value", "Value"); break;
        }
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
}