#define AppVersion "1.0.0"

[Setup]
AppId={{7C4E9A21-6B18-4F3D-9A55-2E8D1C0B7F44}
AppName=MediCore
AppVersion={#AppVersion}
AppPublisher=MediCore
VersionInfoVersion=1.0.0.0
DefaultDirName=C:\MediCore
DefaultGroupName=MediCore
SetupIconFile=assets\medicore.ico
UninstallDisplayIcon={app}\medicore.ico
DisableProgramGroupPage=yes
PrivilegesRequired=admin
ArchitecturesAllowed=x64compatible
ArchitecturesInstallIn64BitMode=x64compatible
OutputDir=dist
OutputBaseFilename=MediCore-Setup-{#AppVersion}
Compression=lzma2
SolidCompression=yes
WizardStyle=modern
CloseApplications=no
RestartApplications=no
DirExistsWarning=no
UsePreviousAppDir=yes
MinVersion=10.0
SetupLogging=yes
ExtraDiskSpaceRequired=1610612736

[Languages]
Name: "english"; MessagesFile: "compiler:Default.isl"

[Messages]
WelcomeLabel2=Install MediCore on this computer.%n%nThe program runs in your browser. You do not need Git, Node.js, Composer, or a separate database server.
FinishedHeadingLabel=Installation completed successfully.
FinishedLabel=MediCore is ready on this computer.%n%nChoose Launch MediCore to open it.

[Files]
Source: "staging\*"; DestDir: "{app}"; Flags: recursesubdirs createallsubdirs ignoreversion
Source: "assets\medicore.ico"; DestDir: "{app}"; Flags: ignoreversion

[InstallDelete]
Type: files; Name: "{autodesktop}\HMS Pharmacy.lnk"
Type: files; Name: "{autoprograms}\HMS Pharmacy.lnk"

[Icons]
Name: "{autodesktop}\MediCore"; Filename: "{sys}\wscript.exe"; Parameters: """{app}\scripts\launch.vbs"""; WorkingDir: "{app}"; IconFilename: "{app}\medicore.ico"
Name: "{autoprograms}\MediCore"; Filename: "{sys}\wscript.exe"; Parameters: """{app}\scripts\launch.vbs"""; WorkingDir: "{app}"; IconFilename: "{app}\medicore.ico"

[Run]
Filename: "{sys}\wscript.exe"; Parameters: """{app}\scripts\launch.vbs"""; Description: "Launch MediCore"; Flags: postinstall nowait skipifsilent

[UninstallRun]
Filename: "powershell.exe"; Parameters: "-NoProfile -ExecutionPolicy Bypass -File ""{app}\scripts\uninstall.ps1"""; Flags: runhidden waituntilterminated; RunOnceId: "StopHmsServices"

[Code]
function PrepareToInstall(var NeedsRestart: Boolean): String;
var
  ResultCode: Integer;
  Script: String;
begin
  Result := '';
  Script := ExpandConstant('{app}\scripts\prepare-upgrade.ps1');
  if FileExists(Script) then
  begin
    if (not Exec('powershell.exe', '-NoProfile -ExecutionPolicy Bypass -File "' + Script + '"', '', SW_HIDE, ewWaitUntilTerminated, ResultCode)) or (ResultCode <> 0) then
      Result := 'The existing database could not be backed up, so the update was stopped.';
  end;
end;

procedure CurStepChanged(CurStep: TSetupStep);
var
  ResultCode: Integer;
begin
  if CurStep = ssPostInstall then
  begin
    if (not Exec('powershell.exe', '-NoProfile -ExecutionPolicy Bypass -File "' + ExpandConstant('{app}\scripts\install.ps1') + '"', '', SW_HIDE, ewWaitUntilTerminated, ResultCode)) or (ResultCode <> 0) then
      MsgBox('Installation did not finish. See ' + ExpandConstant('{app}\logs\install.log'), mbError, MB_OK);
  end;
end;

function InitializeUninstall(): Boolean;
var
  Flag: String;
  DeleteData: Boolean;
begin
  Result := MsgBox('Remove MediCore from this computer?' + #13#10 + #13#10 + 'The program will be removed. Database, storage and backups can stay on this computer.', mbConfirmation, MB_YESNO) = IDYES;
  if not Result then
    exit;

  DeleteData := MsgBox('Also delete the pharmacy database, storage and backups?' + #13#10 + #13#10 + 'No keeps them. Yes deletes them permanently.', mbConfirmation, MB_YESNO or MB_DEFBUTTON2) = IDYES;
  Flag := ExpandConstant('{app}\config\uninstall-delete-data.flag');
  if DeleteData then
    SaveStringToFile(Flag, '1', False)
  else
    SaveStringToFile(Flag, '0', False);
end;
