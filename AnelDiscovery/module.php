<?php

declare(strict_types=1);

class AnelDiscovery extends IPSModule
{
    
    /**
     * The maximum number of seconds that will be allowed for the discovery request.
     */
    const WS_DISCOVERY_TIMEOUT = 5;
    const WS_DISCOVERY_MULTICAST_ADDRESS = '255.255.255.255';
    const WS_DISCOVERY_MULTICAST_PORT = 75;

    public function Create()
    {
        //Never delete this line!
        parent::Create();
        $this->RegisterPropertyString('Username', 'admin');
        $this->RegisterPropertyString('Password', 'anel');
    }

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();
    }

    public function GetConfigurationForm()
    {
        $Form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        $Devices = $this->discoverDevices();

        $Values = [];

        foreach ($Devices as $Device) {
            $instanceID = $this->getAnelInstances($Device['IP']);

            $AddValue = [
                'IPAddress'             => $Device['IP'],
                'name'                  => $Device['deviceName'],
                'instanceID'            => $instanceID
            ];

            $AddValue['create'] = [
                [
                    'moduleID'      => '{13F0B37E-30C9-C043-A5AC-2D9B6A90E9F2}',
                    'configuration' => [
                        'IPAddress' => $Device['IPv4'],
                        'Username' => $this->ReadPropertyString('Username'),
                        'Password' => $this->ReadPropertyString('Password')
                    ]
                ],
            ];

            $Values[] = $AddValue;
        }
        $Form['actions'][0]['values'] = $Values;
        return json_encode($Form);
    }
    
    public function discoverDevices()
    {
        $discoveryTimeout = time() + self::WS_DISCOVERY_TIMEOUT;
        $discoveryMessage = 'wer da?';
        $discoveryPort = self::WS_DISCOVERY_MULTICAST_PORT;
        $discoveryList = [];

        $sock = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        socket_set_option($sock, SOL_SOCKET, SO_REUSEADDR, 1);
        socket_set_option($sock, SOL_SOCKET, SO_RCVTIMEO, ['sec' => 0, 'usec' => 100000]);
        socket_set_option($sock, SOL_SOCKET, SO_BROADCAST, 1);

        socket_bind($sock, '0.0.0.0', 77);
        $this->SendDebug('Start Discovery', '', 0);
        socket_sendto($sock, $discoveryMessage, strlen($discoveryMessage), 0, self::WS_DISCOVERY_MULTICAST_ADDRESS, self::WS_DISCOVERY_MULTICAST_PORT);
        $response = $from = null;
        do {
            if (0 == @socket_recvfrom($sock, $response, 9999, 0, $from, $discoveryPort)) {
                continue;
            }
            $this->SendDebug('Receive', $response, 0);
            $response = explode(":", utf8_decode($response));
            $Device = [];
            $Device['IP'] = $from;
            $Device['deviceName'] = $response[1];
            array_push($discoveryList, $Device);
            $this->SendDebug('Receive from', $from, 0);
            usleep(10000);
        } while (time() < $discoveryTimeout);
        socket_close($sock);
        return $discoveryList;
    }

    private function getAnelInstances($IPAddress)
    {
        $InstanceIDs = IPS_GetInstanceListByModuleID('{13F0B37E-30C9-C043-A5AC-2D9B6A90E9F2}');
        foreach ($InstanceIDs as $id) {
            if (IPS_GetProperty($id, 'IPAddress') == $IPAddress) {
                return $id;
            }
        }
        return 0;
    }
}
