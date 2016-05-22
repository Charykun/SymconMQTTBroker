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
        if(IPS_GetProperty($ConnectionID, "Port") == 1024)
        {
            IPS_SetProperty($ConnectionID, "Port", 1883);
        }
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
            do{$data = $this->Message_Read($data);}
            while($data != null);
        }
    }

    /** *** WORKAROUND ***
     * SendDataToChildren
     * @param string $JSONString
     */
    protected function SendDataToChildren($JSONString)
    {
        //parent::SendDataToChildren($Data);
        include_once(__DIR__ . "/../MQTTTopic/module.php");
        $ModuleID_r = IPS_GetInstanceListByModuleID("{9D2ACF7A-3A59-4BC9-9DE2-5D87D37A2C48}");
        foreach ($ModuleID_r as $value)
        {
            $Device = new MQTTopic($value);
            $Device->ReceiveData($JSONString);
        }
    }

    private function Message_Read($read)
    {
        $read_fh_bytes = 2;
        $read_more_length_bytes = 3;
        $read_bytes = 0;
        $read_message = substr($read , 0, $read_fh_bytes);
        $read = substr($read, $read_fh_bytes);
        $read_bytes += $read_fh_bytes;
        $cmd = $this->ParseCommand(ord($read_message[0]));
        $message_type = $cmd['message_type'];
        $flags = $cmd['flags'];
        if (ord($read_message[1]) > 0x7f)
        {
            $read_message .= substr($read , 0, $read_more_length_bytes);
            $read = substr($read, $read_more_length_bytes);
            $read_bytes += $read_more_length_bytes;
        }
        $pos = 1;
        $remaining_length = $this->DecodeLength($read_message, $pos);
        $to_read = 0;
        if ($remaining_length)
        {
            $to_read = $remaining_length - ($read_bytes - $pos);
        }
        $read_message .= substr($read , 0, $to_read);
        $read = substr($read, $to_read);
        $this->SendDebug("Message Type", $message_type, 0);
        $this->SendDebug("Message Flags", $flags, 0);
        switch($message_type)
        {
            case Message::CONNECT:
                $Buffer = chr(0x20) . chr(0x02) . chr(0x00) . chr(0x00);
                break;
            case Message::SUBSCRIBE:
                $Buffer = chr(0x90).chr(0x03).chr(0x00).chr(0x00).chr(0x00);
                $this->SendDebug("Subscribe", $this->DecodeString(substr($read_message, 4)), 0);
                break;
            case Message::PUBLISH:
                $offset = 2;
                $topic = $this->DecodeString(substr($read_message, $offset));
                $offset += strlen($topic) + 2;
                $msg = substr($read_message, $offset);
                $this->SendDataToChildren(json_encode(Array("DataID" => "{60502747-993B-492A-BAD0-C61F07CEFADB}", "Topic" => utf8_encode($topic), "Msg" => utf8_encode($msg))));
                $this->SendDebug("Topic: " . $topic, $msg, 0);
                break;
            case Message::PINGREQ:
                $Buffer = chr(0xD0) . chr(0x00);
                break;
        }
        if(isset($Buffer))
        {
            $this->SendDataToParent(json_encode(Array("DataID" => "{79827379-F36E-4ADA-8A95-5F8D1DC92FA9}", "Buffer" => utf8_encode($Buffer))));
        }
        return $read;
    }

    private function ParseCommand($cmd)
    {
        $message_type = $cmd >> 4;
        $flags = $cmd & 0x0f;
        return array('message_type' => $message_type, 'flags' => $flags);
    }

    private function DecodeLength($msg, &$pos)
    {
        $multiplier = 1;
        $value = 0 ;
        do
        {
            $digit = ord($msg[$pos]);
            $value += ($digit & 0x7F) * $multiplier;
            $multiplier *= 0x80;
            $pos++;
        }
        while (($digit & 0x80) != 0);
        return $value;
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

    /**
     * ForwardData
     * @param $JSONString
     */
    public function ForwardData($JSONString)
    {
        $data = json_decode($JSONString);
        if($data->DataID == "{3EE22410-A758-41D9-95CE-AEF3D293FC5D}")
        {
            $this->Publish($data->Topic, $data->Content, $data->QOS, $data->Retrain);            
        }        
    }

    private function Publish($topic, $content, $qos = 0, $retrain = 0)
    {
        $i = 0;
        $buffer = "";
        $buffer .= $this->StrWriteString($topic, $i);
        if($qos)
        {
            $id = 0;
            $buffer .= chr($id >> 8); $i++;
            $buffer .= chr($id % 256); $i++;
        }
        $buffer .= $content;
        $i += strlen($content);
        $head = " ";
        $cmd = 0x30;
        if($qos)
        {
            $cmd += $qos << 1;
        }
        if($retrain)
        {
            $cmd += 1;
        }
        $head{0} = chr($cmd);
        $head .= $this->SetMsgLength($i);
        $this->SendDebug("TRANSMIT", $head . $buffer, 0);
        return $this->SendDataToParent(json_encode(Array("DataID" => "{79827379-F36E-4ADA-8A95-5F8D1DC92FA9}", "Buffer" => $head . $buffer)));
    }

    private function StrWriteString($str, &$i)
    {
        $len = strlen($str);
        $msb = $len >> 8;
        $lsb = $len % 256;
        $ret = chr($msb);
        $ret .= chr($lsb);
        $ret .= $str;
        $i += ($len+2);
        return $ret;
    }

    private function SetMsgLength($len)
    {
        $string = "";
        do
        {
            $digit = $len % 128;
            $len = $len >> 7;
            if($len > 0) $digit = ($digit | 0x80);
            $string .= chr($digit);
        }
        while($len > 0);
        return $string;
    }
}

class Message
{
    const CONNECT       = 0x01;
    const PUBLISH       = 0x03;
    const SUBSCRIBE     = 0x08;
    const PINGREQ       = 0x0C;
}