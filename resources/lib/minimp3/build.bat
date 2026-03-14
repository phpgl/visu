@echo off
REM Build minimp3 shared library for PHP FFI (Windows)
REM Run from a Visual Studio Developer Command Prompt, or with MinGW/gcc in PATH.

cd /d "%~dp0"

where cl >nul 2>nul
if %errorlevel% equ 0 (
    echo Building with MSVC...
    cl /O2 /LD /Fe:minimp3.dll minimp3_wrapper.c
    del minimp3_wrapper.obj minimp3.lib minimp3.exp 2>nul
    echo Built minimp3.dll
    goto :done
)

where gcc >nul 2>nul
if %errorlevel% equ 0 (
    echo Building with GCC...
    gcc -shared -O2 -o minimp3.dll minimp3_wrapper.c
    echo Built minimp3.dll
    goto :done
)

echo Error: No C compiler found. Install Visual Studio Build Tools or MinGW.
exit /b 1

:done
