<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\OfficeDocumentEditor;

use Aurora\System\SettingsProperty;

/**
 * @property bool $Disabled
 * @property bool $IncludeInMobile
 * @property array $ExtensionsToView
 * @property array $ExtensionsToConvert
 * @property array $ExtensionsToEdit
 * @property string $DocumentServerUrl
 * @property string $Secret
 * @property bool $EnableHistory
 * @property string $TrustedServerHost
 */

class Settings extends \Aurora\System\Module\Settings
{
    protected function initDefaults()
    {
        $this->aContainer = [
            "Disabled" => new SettingsProperty(
                false,
                "bool",
                null,
                "Setting to true disables the module",
            ),
            "IncludeInMobile" => new SettingsProperty(
                true,
                "bool",
                null,
                "If true, the module is used in mobile version of the interface",
            ),
            "ExtensionsToView" => new SettingsProperty(
                [
                    "pdf",
                    "rtf",
                    "csv",
                    "txt",
                    "html",
                    "htm",
                    "mht",
                    "djvu",
                    "fb2",
                    "epub",
                    "xps"
                ],
                "array",
                null,
                "List of file types which can only be viewed by OnlyOffice",
            ),
            "ExtensionsToConvert" => new SettingsProperty(
                [
                    "doc" => "docx",
                    "xls" => "xlsx",
                    "pps" => "pptx",
                    "ppt" => "pptx",
                    "odt" => "docx",
                    "odp" => "pptx",
                    "ods" => "xlsx",
                    "xlt" => "xlsx",
                    "fods" => "xlsx",
                    "ots" => "xlsx",
                    "ott" => "docx",
                    "fodt" => "docx",
                    "dot" => "docx",
                    "otp" => "pptx",
                    "fodp" => "pptx",
                    "pot" => "pptx",
                    "docm" => "docx",
                    "dotx" => "docx",
                    "dotm" => "docx",
                    "xltx" => "xlsx",
                    "xltm" => "xlsx",
                    "xlsm" => "xlsx",
                    "potx" => "pptx",
                    "potm" => "pptx",
                    "pptm" => "pptx",
                    "ppsx" => "pptx",
                    "ppsm" => "pptx"
                ],
                "array",
                null,
                "List of file types which can only be edited by OnlyOffice into a different format, and target format specified for each entry",
            ),
            "ExtensionsToEdit" => new SettingsProperty(
                [
                    "docx",
                    "xlsx",
                    "pptx"
                ],
                "array",
                null,
                "List of file types which can be edited by OnlyOffice",
            ),
            "DocumentServerUrl" => new SettingsProperty(
                "",
                "string",
                null,
                "OnlyOffice installation URL",
            ),
            "Secret" => new SettingsProperty(
                "",
                "string",
                null,
                "Secret token needs to be specified here if JWT protection is enabled on OnlyOffice",
            ),
            "EnableHistory" => new SettingsProperty(
                true,
                "bool",
                null,
                "Enables Version History feature of document editing",
            ),
            "TrustedServerHost" => new SettingsProperty(
                "",
                "string",
                null,
                "Reserved for future use",
            ),
        ];
    }
}
