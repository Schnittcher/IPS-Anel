<?php

declare(strict_types=1);
class AnelHUT extends IPSModule
{
    public function Create()
    {
        //Never delete this line!
        parent::Create();

        $this->ConnectParent('{82347F20-F541-41E1-AC5B-A636FD3AE2D8}');
        $this->RegisterPropertyString('IPAddress', '');
        $this->RegisterPropertyString('Username', '');
        $this->RegisterPropertyString('Password', '');
        $this->RegisterPropertyBoolean('IOs', false);
    }

    public function Destroy()
    {
        //Never delete this line!
        parent::Destroy();
    }

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();
        $this->SetReceiveDataFilter('.*' . $this->ReadPropertyString('IPAddress') . '.*');

        $this->RegisterVariableFloat('DeviceTemperature', $this->Translate('Temperature from Device'), '~Temperature');
    }

    public function ReceiveData($JSONString)
    {
        $data = json_decode($JSONString);
        $result = explode(':', utf8_decode($data->Buffer));

        $this->SendDebug(__FUNCTION__, utf8_decode($data->Buffer), 0);

        $indexRelais = 5;
        $indexIOs = 15;
        for ($i = 1; $i <= 8; $i++) {
            //Relais
            $indexRelais++;
            $this->RegisterVariableBoolean('Relay' . $i, strstr($result[$indexRelais], ',', true), '~Switch');
            $this->EnableAction('Relay' . $i);
            $tmpValue = explode(',', $result[$indexRelais]);
            $this->SetValue('Relay' . $i, $tmpValue[1]);

            //IOs
            if ($this->ReadPropertyBoolean('IOs')) {
                $indexIOs++;
                $this->RegisterVariableBoolean('IO' . $i, strstr($result[$indexIOs], ',', true), '~Switch');
                $tmpValue = explode(',', $result[$indexIOs]);
                if ($tmpValue[1] == 0) {
                    $this->EnableAction('IO' . $i);
                } else {
                    $this->DisableAction('IO' . $i);
                }
                $this->SetValue('IO' . $i, $tmpValue[2]);
            }
        }

        //Index 24 Temperatur
        $DeviceTemperature = str_replace('°C', '', $result[24]);
        $this->SetValue('DeviceTemperature', floatval($DeviceTemperature));

        //Index 26 DeviceTyp
        $DeviceTyp = $result[26]; //a = ADV; i = IO; h = HUT ; o = ONE

        //Index 27 Power Metering //p = yes; n = no
        $PowerMetering = $result[27];
        $SensorIndex = 0;
        switch ($PowerMetering) {
            case 'p':
                //$this->LogMessage('Power Metering active', KL_NOTIFY);
                //Index für Sensor 34
                $SensorIndex = 34;
                break;
            case 'n':
                //$this->LogMessage('Power Metering inactive', KL_NOTIFY);
                //Index für Sensor 28
                $SensorIndex = 28;
                break;
            default:
                //$this->LogMessage('Wrong Power Metering Information: '.$PowerMetering, KL_ERROR);
                break;
        }
        //Sensor yes or no
        if ($result[$SensorIndex] == 's') {
            $this->RegisterVariableFloat('SensorTemperature', $this->Translate('Sensor Temperature'), '~Temperature');
            $this->RegisterVariableFloat('SensorHumidity', $this->Translate('Sensor Humidity'), '~Humidity.F');
            $this->RegisterVariableInteger('SensorBrightness', $this->Translate('Sensor Brightness'), '~Illumination');

            $this->SetValue('SensorTemperature', floatval($result[$SensorIndex + 1]));
            $this->SetValue('SensorHumidity', floatval($result[$SensorIndex + 2]));
            $this->SetValue('SensorBrightness', intval($result[$SensorIndex + 3]));
        }
    }

    public function GetConfigurationForParent()
    {
        $Config['BindPort'] = 77;
        $Config['Port'] = 75;
        $Config['BindIP'] = '0.0.0.0';
        $Config['EnableBroadcast'] = 0;
        $Config['EnableReuseAddress'] = 1;
        $json = json_encode($Config);
        return $json;
    }

    public function RequestAction($Ident, $Value)
    {
        if (fnmatch('Relay*', $Ident)) {
            $relais = substr($Ident, -1, 1);
            $this->setRelais(intval($relais), $Value);
        }

        if (fnmatch('IO*', $Ident)) {
            $relais = substr($Ident, -1, 1);
            $this->setIO(intval($relais), $Value);
        }
    }
    private function Send(string $buffer)
    {
        $this->SendDataToParent(json_encode(['DataID' => '{C8792760-65CF-4C53-B5C7-A30FCC84FEFE}', 'ClientIP' => $this->ReadPropertyString('IPAddress'), 'ClientPort' => 75, 'Buffer' => $buffer]));
    }

    private function setRelais(int $relais, bool $value)
    {
        if ($value) {
            $payload = 'Sw_on' . $relais . $this->ReadPropertyString('Username') . $this->ReadPropertyString('Password');
        } else {
            $payload = 'Sw_off' . $relais . $this->ReadPropertyString('Username') . $this->ReadPropertyString('Password');
        }
        $this->Send($payload);
    }

    private function setIO(int $io, bool $value)
    {
        if ($value) {
            $payload = 'IO_on' . $io . $this->ReadPropertyString('Username') . $this->ReadPropertyString('Password');
        } else {
            $payload = 'IO_off' . $io . $this->ReadPropertyString('Username') . $this->ReadPropertyString('Password');
        }
        $this->Send($payload);
    }
}