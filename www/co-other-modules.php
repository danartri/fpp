<script>
/*
Quick instructions for adding new output types
If needed extend a class to add input fields on the Output Config column.  Create a new object for the output type and it to the output_modules array at the bottom.  No need to edit co-other.php.
Name = type, fpp uses this name to load the correct module
friendlyName = is the display name for the type and is what is displayed in the Output Type column
maxChannels = is the max channels that can be configured.  It is also the default channel count for a new row.
fixChans = true when the output has a fixed channel count.
*/

/////////////////////////////////////////////////////////////////////////////
//Base Class

class OtherBase {
    constructor (name="Base-Type", friendlyName="Base Name", maxChannels=512, fixedChans=false, config = {}) {
        this._typeName = name;
        this._typeFriendlyName = friendlyName;
        this._maxChannels = maxChannels;
        this._fixedChans = fixedChans;
        this._config = config;
    }
    
    PopulateHTMLRow(config) {
        var results = "";
        return results;
    }

    AddNewRow() {
        return this.PopulateHTMLRow(this._config);
    }

    GetOutputConfig(result, cell) {
        return result;
    }

    SetDefaults(row) {
        row.find("td input.count").val(this._maxChannels);
        if(this._fixedChans)
            row.find("td input.count").prop('disabled', true);
    }

    CanAddNewOutput() {
        return true;
    }

    get typeName () {
        return this._typeName;
    }

    get typeFriendlyName () {
        return this._typeFriendlyName;
    }

    get maxChannels () {
        return this._maxChannels;
    }

    get fixedChans () {
        return this._fixedChans;
    }
}

/////////////////////////////////////////////////////////////////////////////
//Base Class for outputs with a "Port" device such as serial and SPI
class OtherBaseDevice extends OtherBase {
    constructor (name="Base-Type-Device", friendlyName="Base Device Name", maxChannels=512, fixedChans=false, devices=["No Devices"], config = {}) {
        super(name, friendlyName, maxChannels, fixedChans, config)
        this._devices = devices;
    }
    
    PopulateHTMLRow(config) {
        var results = "";
        results += DeviceSelect(this._devices, config.device);
        return results;
    }

    GetOutputConfig(result, cell) {
        var device = cell.find("select.device").val();

        if (device == "")
            return "";

        result.device = device;
        return result;
    }

    CanAddNewOutput() {
        if (this._devices[0] == "No Devices" || Object.keys(this._devices).length == 0)
            return false;
        return true;
    }
}

/////////////////////////////////////////////////////////////////////////////
// Misc. Support functions
function CreateSelect(optionArray = ["No Options"], currentValue, selectTitle, dropDownTitle, selectClass) {
	var result = selectTitle+": <select class='"+selectClass+"'>";

	if (currentValue == "")
		result += "<option value=''>"+dropDownTitle+"</option>";

	var found = 0;
	for (var key in optionArray) {
		result += "<option value='" + key + "'";
	
		if (currentValue == key) {
			result += " selected";
			found = 1;
		}

		result += ">" + optionArray[key] + "</option>";
	}

	if ((currentValue != '') &&
		(found == 0)) {
		result += "<option value='" + currentValue + "'>" + currentValue + "</option>";
	}
	result += "</select>";

	return result;
}

function DeviceSelect(deviceArray = ["No Devices"], currentValue) {
    return CreateSelect (deviceArray, currentValue, "Port", "-- Port --", "device");
}



/////////////////////////////////////////////////////////////////////////////
// SPI and Serial Devices

var SPIDevices = new Array();
<?
        foreach(scandir("/dev/") as $fileName)
        {
            if (preg_match("/^spidev[0-9]/", $fileName)) {
                echo "SPIDevices['$fileName'] = '$fileName';\n";
            }
        }
?>

var SerialDevices = new Array();
<?
	foreach(scandir("/dev/") as $fileName)
	{
		if ((preg_match("/^ttyS[0-9]+/", $fileName)) ||
			(preg_match("/^ttyACM[0-9]+/", $fileName)) ||
			(preg_match("/^ttyO[0-9]/", $fileName)) ||
			(preg_match("/^ttyS[0-9]/", $fileName)) ||
			(preg_match("/^ttyAMA[0-9]+/", $fileName)) ||
			(preg_match("/^ttyUSB[0-9]+/", $fileName))) {
			echo "SerialDevices['$fileName'] = '$fileName';\n";
		}
	}
?>

var I2CDevices = new Array();
<?
        foreach(scandir("/dev/") as $fileName)
        {
            if (preg_match("/^i2c-[0-9]/", $fileName)) {
                echo "I2CDevices['$fileName'] = '$fileName';\n";
            }
        }
?>



/////////////////////////////////////////////////////////////////////////////
// WS2801 Output via SPI

class SPIws2801Device extends OtherBaseDevice {
    
    constructor() {
        super("SPIws2801", "SPI-WS2801", 1530, false, SPIDevices, {pi36: 0});
    }

    PopulateHTMLRow(config) {
        var result = super.PopulateHTMLRow(config);

        result += " PI36: <input type=checkbox class='pi36'";
        if (config.pi36)
            result += " checked='checked'";

        result += ">";

        return result;
    }

    GetOutputConfig(result, cell) {
        result = super.GetOutputConfig(result, cell);
       
        if (result == "")
            return "";

        var pi36 = 0;

        if (cell.find("input.pi36").is(":checked"))
            pi36 = 1;

        result.pi36 = parseInt(pi36);

        return result;
        }

}

/////////////////////////////////////////////////////////////////////////////
// I2C Output
class I2COutput extends OtherBaseDevice {

    constructor(name="I2C-Output", friendlyName="I2C Output", maxChannels=512, fixedChans=false, config = {deviceID: 0x40}, minAddr = 0x00, maxAddr = 0x7f) {
        super(name, friendlyName, maxChannels, fixedChans, I2CDevices, config);
        this._minAddr = minAddr;
        this._maxAddr = maxAddr;
    }

    PopulateHTMLRow(config) {
        var result = super.PopulateHTMLRow(config);

        var addrs = {};
        //build addr array for select option
        for (var addr = this._minAddr; addr <= this._maxAddr; addr++) {
            addrs[addr] = "Hex: 0x"+addr.toString(16)+", Dec: "+addr;
        }

        result += " "+CreateSelect(addrs, config.deviceID, "I2C Address", "Select Address", "addr");

        return result;
    }

    GetOutputConfig(result, cell) {
        result = super.GetOutputConfig(result, cell);
        var addr = cell.find("select.addr").val();
        result.deviceID = parseInt(addr);
        return result;
    }        
}

class PCA9685Output extends I2COutput {
    constructor(name="PCA9685", friendlyName="PCA9685", maxChannels=32, fixedChans=false, config = {device:"i2c-1", deviceID: 0x40, frequency: 50}) {
        super(name, friendlyName, maxChannels, fixedChans, config);
    }
    PopulateHTMLRow(config) {
        var result = super.PopulateHTMLRow(config);
        var datatypes = ["8 Bit Scaled", "8 Bit Scaled Reversed", "16 Bit Scaled", "16 Bit Scaled Reversed", "8 Bit Absolute", "16 Bit Absolute"];
        
        var inMicrosecs = config.asUsec;
        if (inMicrosecs == undefined) {
            inMicrosecs = true;
        }
        result += " Frequency (Hz): <input class='frequency' type='number' min='40' max='1600' value='" + config.frequency + "'/><br><input class='asUsec' type='checkbox' " + (inMicrosecs ? "checked" : "") + ">Min/Max in micro-seconds</input><br>";
        
        for (var x = 0; x < 16; x++) {
            var min = 500;
            var max = 2500;
            var dataType = 0;
            
            if (config.ports != undefined && config.ports[x] != undefined) {
                min = config.ports[x].min;
                max = config.ports[x].max;
                dataType = config.ports[x].dataType;
            }
            
            result += "<br>Port " + x + ": ";
            result += "Min Value:<input class='min" + x + "' type='number' min='0' max='4095' value='" + min + "'/>";
            result += " Max Value:<input class='max" + x + "' type='number' min='0' max='4095' value='" + max + "'/> ";
            result += CreateSelect(datatypes, dataType, "Data Type", "Select Data Type", "dataType" + x);
        }

        return result;
    }
    GetOutputConfig(result, cell) {
        result = super.GetOutputConfig(result, cell);
        result.frequency = parseInt(cell.find("input.frequency").val());
        result.asUsec = cell.find("input.asUsec").is(":checked") ? 1 : 0;
        result.ports = [];
        for (var x = 0; x < 16; x++) {
            var dt = cell.find("select.dataType" + x).val();
            result.ports[x] = {};
            result.ports[x].dataType = parseInt(dt);
            result.ports[x].min = parseInt(cell.find("input.min" + x).val());
            result.ports[x].max = parseInt(cell.find("input.max" + x).val());
        }

        
        return result;
    }

}

/////////////////////////////////////////////////////////////////////////////
// Generic SPI Output
class GenericSPIDevice extends OtherBaseDevice {
    
    constructor(name="GenericSPI", friendlyName="Generic SPI", maxChannels=1048576, fixedChans=false, devices=SPIDevices, config={speed: 50}) {
        super(name, friendlyName, maxChannels, fixedChans, devices, config);
    }

    PopulateHTMLRow(config) {
        var result = super.PopulateHTMLRow(config);
        result += " Speed (khz): <input type='number' name='speed' min='1' max='999999' class='speed' value='"+config.speed+"'>";
        return result;
    }

    GetOutputConfig(result, cell) {
        result = super.GetOutputConfig(result, cell);
        var speed = cell.find("input.speed").val();
       
        if (result == "" || speed == "")
            return "";

        result.speed = speed;

        return result;
    }
}

/////////////////////////////////////////////////////////////////////////////
// Generic UDP Output
class GenericUDPDevice extends OtherBase {
    
    constructor(name="GenericUDP", friendlyName="Generic UDP", maxChannels=300, fixedChans=false, config={address: "", port:8080, tokens:""}) {
        super(name, friendlyName, maxChannels, fixedChans, config);
    }

    PopulateHTMLRow(config) {
        var result = super.PopulateHTMLRow(config);
        result += "IP: <input type='text' name='address' class='address' value='"+config.address+"'>&nbsp;";
        result += "Port: <input type='number' name='port' min='1' max='65536' class='port' value='"+config.port+"'>&nbsp;";
        result += "Data: <input type='text' class='tokens' name='tokens' value='"+config.tokens+"' size='50' maxLength='256'><br>";
        result += "Data tokens include {data} for channel data, {S1} or {S2} for onebyte or two bit channel count, or 0x00 for hex byte";
        return result;
    }

    GetOutputConfig(result, cell) {
        result = super.GetOutputConfig(result, cell);
        var port = cell.find("input.port").val();
        result.port = parseInt(port);
        result.address =  cell.find("input.address").val();
        result.tokens =  cell.find("input.tokens").val();

        return result;
    }

}


/////////////////////////////////////////////////////////////////////////////
//populate the output devices
var output_modules = [];

//Outputs for all platforms

//Outputs for Raspberry Pi or Beagle
<?
if ($settings['Platform'] == "Raspberry Pi" || $settings['Platform'] == "BeagleBone Black")
{
?>
    output_modules.push(new I2COutput("MCP23017", "MCP23017", 16, false, {deviceID: 0x20} , 0x20, 0x27));
    output_modules.push(new GenericSPIDevice());
    output_modules.push(new PCA9685Output());
<?
}
?>
//Outputs for Raspberry Pi
<?
if ($settings['Platform'] == "Raspberry Pi")
{
?>
    //TODO need to see if these modules could run as is on the BeagleBone
    output_modules.push(new SPIws2801Device());
<?
}
?>
    output_modules.push(new GenericUDPDevice());


</script>
