<?php

class MQTTBroker extends IPSModule
{
    /**
     * Create
     */         
    public function Create()
    {
        //Never delete this line!
        parent::Create();

        $this->RequireParent("{8062CF2B-600E-41D6-AD4B-1BA66C32D6ED}");
        $Instance = IPS_GetInstance($this->InstanceID);
        $ConnectionID = $Instance["ConnectionID"];
        IPS_SetName($ConnectionID, "MQTT Socket");
        IPS_SetProperty($ConnectionID, "Port", 1883);
        IPS_ApplyChanges($ConnectionID);
    }

    /**
     * ApplyChanges
     */
    public function ApplyChanges()
    {
        //Never delete this line! 
        parent::ApplyChanges();
    }

    /**
     * ReceiveData
     * @param $JSONString
     */
    public function ReceiveData($JSONString)
    {
        $data = json_decode($JSONString);
        if($data->DataID == "{018EF6B5-AB94-40C6-AA53-46943E824ACF}")
        {
            $data = utf8_decode($data->Buffer);
            $this->SendDebug("RECEIVED", $data, 0);
            $header = $this->MQTT_Get_Header($data);
            $this->SendDebug("MQTT Header Type", $header["type"], 0);
            switch ($header["type"])
            {
                case  1:
                    $Buffer = chr(0x20) . chr(0x02) . chr(0x00) . chr(0x00);
                    $ConnectInfo = $this->MQTT_Connect($header, substr($data, 2));
                    $this->SendDebug("MQTT Version", $ConnectInfo["version"], 0);
                    break;
                case  8:
                    $Buffer = chr(0x90).chr(0x03).chr(0x00).chr(0x00).chr(0x00);
                    $this->SendDebug("Subscribe", $this->DecodeString(substr($data, 4)), 0);
                    break;
                case 3:
                    $offset = 2;
                    $topic = $this->DecodeString(substr($data, $offset));
                    $offset += strlen($topic) + 2;
                    $msg = substr($data, $offset);
                    $this->SendDataToChildren(json_encode(Array("DataID" => "{60502747-993B-492A-BAD0-C61F07CEFADB}", "Topic" => utf8_encode($topic), "Msg" => utf8_encode($msg))));
                    $this->SendDebug("Topic: " . $topic, $msg, 0);
                    break;
                case 12:
                    $Buffer = chr(0xD0) . chr(0x00);
                    break;
            }
            if(isset($Buffer))
            {
                $this->SendDataToParent(json_encode(Array("DataID" => "{79827379-F36E-4ADA-8A95-5F8D1DC92FA9}", "Buffer" => utf8_encode($Buffer))));
            }
        }
    }

    private function MQTT_Get_Header($data)
    {
        $byte = ord($data[0]);
        $header["type"] = ($byte & 0xF0) >> 4;
        $header["dup"] = ($byte & 0x08) >> 3;
        $header["qos"] = ($byte & 0x06) >> 1;
        $header["retain"] = $byte & 0x01;
        return $header;
    }

    private function MQTT_Connect($header, $data)
    {
        $connect_info["protocol_name"] = $this->DecodeString($data);
        $offset = strlen($connect_info["protocol_name"]) + 2;
        $connect_info["version"] = ord(substr($data, $offset, 1));
        $offset += 1;
        $byte = ord($data[$offset]);
        $connect_info["willRetain"] = ($byte & 0x20 == 0x20);
        $connect_info["willQos"] = ($byte & 0x18 >> 3);
        $connect_info["willFlag"] = ($byte & 0x04 == 0x04);
        $connect_info["cleanStart"] = ($byte & 0x02 == 0x02);
        $offset += 1;
        $connect_info["keepalive"] = $this->DecodeValue(substr($data, $offset, 2));
        $offset += 2;
        $connect_info["clientId"] = $this->DecodeValue(substr($data, $offset));
        return $connect_info;
    }

    private function DecodeString($data)
    {
        $length = $this->DecodeValue($data);
        return substr($data, 2, $length);
    }

    private function DecodeValue($data)
    {
        return 256 * ord($data[0]) + ord($data[1]);
    }
}