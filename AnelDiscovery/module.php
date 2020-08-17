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

        // Only required for pre 5.5
        if (floatval(IPS_GetKernelVersion()) < 5.5) {
            $this->RegisterTimer('LoadDevicesTimer', 0, 'ANEL_discoverDevices($_IPS["TARGET"]);');
        }

        $this->SetBuffer('discoveredDevices', json_encode([]));
        $this->SetBuffer('SearchActive', json_encode(false));

        $this->RegisterMessage(0, IPS_KERNELSTARTED);
    }

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();
    }

    public function GetConfigurationForm()
    {
        $Form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        $Devices = json_decode($this->GetBuffer('discoveredDevices'), true);

        if (!json_decode($this->GetBuffer('SearchActive'))) {
            $this->SetBuffer('SearchActive', json_encode(true));

            // Start device search in a timer, not prolonging the execution of GetConfigurationForm
            if (floatval(IPS_GetKernelVersion()) < 5.5) {
                $this->SetTimerInterval('LoadDevicesTimer', 200);
            } else {
                $this->SendDebug('Start', 'OnceTimer', 0);
                $this->RegisterOnceTimer('LoadDevicesTimer', 'ANEL_discoverDevices($_IPS["TARGET"]);');
            }
        }

        $Form['actions'][0]['visible'] = count($Devices) == 0;
        $Form['actions'][1]['values'] = $Devices;
        return json_encode($Form);
    }

    public function discoverDevices()
    {
        $this->LogMessage($this->Translate('Discovery in progress'), KL_NOTIFY);
        $discoveryTimeout = time() + self::WS_DISCOVERY_TIMEOUT;
        $discoveryMessage = 'wer da?';
        $discoveryPort = self::WS_DISCOVERY_MULTICAST_PORT;
        $discoveryList = [];

        $Values = [];

        $sock = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        socket_set_option($sock, SOL_SOCKET, SO_REUSEADDR, 1);
        socket_set_option($sock, SOL_SOCKET, SO_RCVTIMEO, ['sec' => 0, 'usec' => 100000]);
        socket_set_option($sock, SOL_SOCKET, SO_BROADCAST, 1);

        socket_bind($sock, '0.0.0.0', 77);
        socket_sendto($sock, $discoveryMessage, strlen($discoveryMessage), 0, self::WS_DISCOVERY_MULTICAST_ADDRESS, self::WS_DISCOVERY_MULTICAST_PORT);
        $response = $from = null;
        do {
            if (0 == @socket_recvfrom($sock, $response, 9999, 0, $from, $discoveryPort)) {
                continue;
            }
            $this->SendDebug('Receive from', $from, 0);
            $this->SendDebug('Receive', $response, 0);
            $response = explode(':', utf8_decode($response));
            $instanceID = $this->getAnelInstances($from);

            $AddValue = [
                'IPAddress'             => $from,
                'name'                  => $response[1],
                'instanceID'            => $instanceID
            ];

            $AddValue['create'] = [
                [
                    'moduleID'      => '{13F0B37E-30C9-C043-A5AC-2D9B6A90E9F2}',
                    'configuration' => [
                        'IPAddress' => $from,
                        'Username'  => $this->ReadPropertyString('Username'),
                        'Password'  => $this->ReadPropertyString('Password')
                    ]
                ],
            ];

            $Values[] = $AddValue;
            usleep(10000);
        } while (time() < $discoveryTimeout);
        socket_close($sock);

        if (floatval(IPS_GetKernelVersion()) < 5.5) {
            $this->SetTimerInterval('LoadDevicesTimer', 0);
        }
        $this->SetBuffer('SearchActive', json_encode(false));
        $this->SetBuffer('discoveredDevices', json_encode($Values));
        $this->UpdateFormField('configurator', 'values', json_encode($Values));
        $this->UpdateFormField('searchingInfo', 'visible', false);

        $this->LogMessage($this->Translate('Discovery progress done'), KL_NOTIFY);
        return;
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
