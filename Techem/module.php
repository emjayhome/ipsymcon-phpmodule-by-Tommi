<?php
/**
 * @file
 *
 * generic Techem Device Module
 *
 * @author Manfred Jantscher
 * @copyright Thomas Dressler 2016-2017, Manfred Jantscher 2018
 * @version 1.0
 * @date 2018-01-14
 */


include_once(__DIR__ . "/../libs/module_helper.php");

/**
 * @class TechemDev
 *
 * generic Techem Device Module Class for IPSymcon
 *
 *
 */
class TechemDev extends T2DModule
{
    //------------------------------------------------------------------------------
    //module const and vars
    //------------------------------------------------------------------------------
    
    /**
     * mapping array for capabilities to variables
     * @var array $capvars
     */
    ///[capvars]
    protected $capvars = array(
        'Name' => array("ident" => 'Name', "type" => self::VT_String, "name" => 'Name', 'profile' => '~String', "pos" => 0),
        'DateLast' => array("ident" => 'DateLast', "type" => self::VT_String, "name" => 'Last Readout', 'profile' => '~String', "pos" => 1),
        'ValueLast' => array("ident" => 'ValueLast', "type" => self::VT_Integer, "name" => 'Last Value', "profile" => '', "pos" => 2),
        'ValueLastHWM' => array("ident" => 'ValueLastHWM', "type" => self::VT_Float, "name" => 'Last Value', "profile" => '', "pos" => 2),
        'DateNow' => array("ident" => 'DateNow', "type" => self::VT_String, "name" => 'Current Readout', 'profile' => '~String', "pos" => 3),
        'ValueNow' => array("ident" => 'ValueNow', "type" => self::VT_Integer, "name" => 'Current Value', "profile" => '', "pos" => 4),
        'ValueNowHWM' => array("ident" => 'ValueNowHWM', "type" => self::VT_Float, "name" => 'Current Value', "profile" => '', "pos" => 4),
        'Temp1' => array("ident" => 'Temp1', "type" => self::VT_Float, "name" => 'Ambient Temperature', "profile" => '~Temperature', "pos" => 5),
        'Temp2' => array("ident" => 'Temp2', "type" => self::VT_Float, "name" => 'Heater Temperature', "profile" => '~Temperature', "pos" => 6),
        'Signal' => array("ident" => 'Signal', "type" => self::VT_Integer, "name" => 'Signal', 'profile' => 'Signal', "pos" => 40,"hidden" => true)
    );
    ///[capvars]
    //------------------------------------------------------------------------------
    //main module functions 
    //------------------------------------------------------------------------------
    /**
     * Constructor
     * @param $InstanceID
     */
    public function __construct($InstanceID)
    {
        // Diese Zeile nicht löschen
        $json = __DIR__ . "/module.json";
        parent::__construct($InstanceID, $json);

    }

    //------------------------------------------------------------------------------
    /**
     * overload internal IPS_Create($id) function
     */
    public function Create()
    {
        // Diese Zeile nicht löschen.
        parent::Create();

        // register property
        $this->RegisterPropertyString('DeviceID', '');
        $this->RegisterPropertyString('Typ', '');
        $this->RegisterPropertyString('Class', '');
        $this->RegisterPropertyString('CapList', '');
        $this->RegisterPropertyBoolean('Debug', false);

        //NonStandard Profiles (needed for Webfront)
        $this->check_profile('Signal', 1, "", " dB", "Gauge", -120, +10, 1, 0, false);
    
        $this->CreateStatusVars();
    }//func

    //------------------------------------------------------------------------------
    /**
     * Destructor
     */
    public function Destroy()
    {

        parent::Destroy();
    }

    //------------------------------------------------------------------------------
    /**
     * overload internal IPS_ApplyChanges($id) function
     */
    public function ApplyChanges()
    {
        // Diese Zeile nicht loeschen
        parent::ApplyChanges();
        if (IPS_GetKernelRunlevel() == self::KR_READY) {
            if ($this->HasActiveParent()) {
                $this->SetStatus(self::ST_AKTIV);
            } else {
                $this->SetStatus(self::ST_NOPARENT);
            } //check status
        }
        //must be here!!
        $this->SetStatusVariables(); //Update Variables
    }

    //------------------------------------------------------------------------------
    //Data Interfaces
    //------------------------------------------------------------------------------
    /**
     * Receive Data from Parent(IO)
     * @param string $JSONString
     */
    public function ReceiveData($JSONString)
    {
        //trigger status check
        if ($this->HasActiveParent()) {
            $this->SetStatus(self::ST_AKTIV);
        } else {
            $this->SetStatus(self::ST_NOPARENT);
        }

        // decode Data from Device Instanz
        if (strlen($JSONString) > 0) {
            // decode Data from IO Instanz
            $this->debug(__FUNCTION__, 'Data arrived:' . $JSONString);
            //$this->debuglog($JSONString);
            // decode Data from IO Instanz
            $data = json_decode($JSONString);
            //entry for data from parent
            if (is_object($data)) $data = get_object_vars($data);
            if (isset($data['DataID'])) {
                $target = $data['DataID'];
                if ($target == $this->module_interfaces['TE-RX']) {
                    if (isset($data['TEData']) && isset($data['DeviceID'])) {
                        $Device = $data['DeviceID'];
                        $typ = $data['Typ'];
                        $class = $data['Class'];
                        //call data point
                        $myID = $this->GetDeviceID();
                        $myType = $this->GetType();
                        $myClass = $this->GetClass();
                        if (($myID == $Device) && ($myType == $typ) && ($myClass == $class)) {
                            $this->debug(__FUNCTION__, "$Device(Typ:$typ,Class:$class)");
                            $sw_data = $data['TEData'];
                            if (is_object($sw_data)) $sw_data = get_object_vars($sw_data);
                            $this->ParseData($sw_data);
                        } 
                    } else {
                        $this->debug(__FUNCTION__, 'Interface Data Error');
                    }
                }
            }
        } else {
            $this->debug(__FUNCTION__, 'strlen(JSONString) == 0');
        }
    }

    //------------------------------------------------------------------------------
    /**
     * Forward command to Splitter parent
     * @param $Data
     * @return bool
     */
    public function SendDataToParent($Data)
    {
        $json = json_encode($Data);
        $this->debug(__FUNCTION__, $json);
        $res = parent::SendDataToParent($json);
        return $res;
    }


    //------------------------------------------------------------------------------
    //Get/Set
    //------------------------------------------------------------------------------
    /**
     * Get Property DeviceID
     * @return string
     */
    private function GetDeviceID()
    {
        return (String)IPS_GetProperty($this->InstanceID, 'DeviceID');
    }

    //------------------------------------------------------------------------------
    /**
     * Get Property Type
     * @return string
     */
    private function GetType()
    {
        return (String)IPS_GetProperty($this->InstanceID, 'Typ');
    }


    //------------------------------------------------------------------------------
    /**
     * GetProperty Modul class of creator
     * @return string
     */
    private function GetClass()
    {
        return (String)IPS_GetProperty($this->InstanceID, 'Class');
    }

    //------------------------------------------------------------------------------
    /**
     * handle incoming data along capabilities
     * @param array $data
     */
    private function ParseData($data)
    {
        //
        $this->debug(__FUNCTION__,'Parse');
        $caps = $this->GetCaps();
        foreach (array_keys($caps) as $cap) {
            $ident = $caps[$cap];
            $vid = @$this->GetIDForIdent($ident);
            if ($vid == 0) {
                $this->debug(__FUNCTION__, "Cap $cap Ident $ident: Variable missed");
                continue;
            }
            if (!isset($data[$cap])) continue;
            $s = $data[$cap];
            $this->debug(__FUNCTION__, "Handle $cap ($vid) = $s");
            switch ($cap) {
                //int types
                case 'Signal': //RSSI
                case 'ValueLast':
                case 'ValueNow':
                    $iv = (int)$s;
                    SetValueInteger($vid, $iv);
                    break;
                //float types
                case 'Temp1':
                case 'Temp2':
                case 'ValueLastHWM':
                case 'ValueNowHWM':
                    $fv = (float)$s;
                    SetValueFloat($vid, $fv);
                    break;
                //string types
                case 'Name':
                case 'DateLast':
                case 'DateNow':
                    $st = utf8_decode($s);
                    SetValueString($vid, $st);
                    break;

                default:
                    $this->debug(__FUNCTION__, "$cap not handled");
            }//switch
            $this->debug(__FUNCTION__, "$cap:($vid)=" . $s);
        }//for
    }//function
}//class
