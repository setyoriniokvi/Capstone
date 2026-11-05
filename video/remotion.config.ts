import { Config } from "@remotion/cli/config";
import fs from "node:fs";

Config.setVideoImageFormat("jpeg");
Config.setOverwriteOutput(true);
// Chromium bawaan sandbox (tidak mengunduh browser baru), bila ada
const chromium = "/opt/pw-browsers/chromium-1194/chrome-linux/chrome";
if (fs.existsSync(chromium)) {
  Config.setBrowserExecutable(chromium);
  Config.setChromeMode("chrome-for-testing");
  Config.setChromiumOpenGlRenderer("angle");
}
