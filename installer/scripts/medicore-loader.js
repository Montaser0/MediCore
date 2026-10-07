const fs = require('fs');
const path = require('path');
const crypto = require('crypto');
const { Readable } = require('stream');

const keyFile = path.resolve(__dirname, '..', '..', 'config', 'content.key');
let key = null;
try {
  const loaded = fs.readFileSync(keyFile);
  if (loaded.length === 32) key = loaded;
} catch (error) {
  key = null;
}
if (!key) return;

const extensions = new Set(['.js', '.mjs', '.cjs', '.json', '.css', '.html', '.htm']);
const origReadFileSync = fs.readFileSync;
const origReadFile = fs.readFile;
const origReadFilePromise = fs.promises.readFile.bind(fs.promises);
const origCreateReadStream = fs.createReadStream;

function inspect(file) {
  return typeof file === 'string' && extensions.has(path.extname(file).toLowerCase());
}

function decrypt(buffer) {
  if (!Buffer.isBuffer(buffer) || buffer.length < 22 || buffer.toString('latin1', 0, 6) !== 'MCENC1') {
    return null;
  }
  const iv = buffer.subarray(6, 22);
  const cipher = buffer.subarray(22);
  const decipher = crypto.createDecipheriv('aes-256-cbc', key, iv);
  return Buffer.concat([decipher.update(cipher), decipher.final()]);
}

function encode(buffer, options) {
  const encoding = typeof options === 'string' ? options : options && options.encoding;
  return encoding ? buffer.toString(encoding) : buffer;
}

fs.readFileSync = function (file, options) {
  if (!inspect(file)) return origReadFileSync(file, options);
  const decoded = decrypt(origReadFileSync(file));
  if (!decoded) return origReadFileSync(file, options);
  return encode(decoded, options);
};

fs.readFile = function (file, options, callback) {
  if (typeof options === 'function') {
    callback = options;
    options = undefined;
  }
  if (!inspect(file)) return origReadFile(file, options, callback);
  return origReadFile(file, (error, buffer) => {
    if (error) return callback(error);
    try {
      const decoded = decrypt(buffer);
      callback(null, encode(decoded || buffer, options));
    } catch (decryptError) {
      callback(decryptError);
    }
  });
};

fs.promises.readFile = async function (file, options) {
  if (!inspect(file)) return origReadFilePromise(file, options);
  const decoded = decrypt(await origReadFilePromise(file));
  if (!decoded) return origReadFilePromise(file, options);
  return encode(decoded, options);
};

fs.createReadStream = function (file, options) {
  if (!inspect(file)) return origCreateReadStream(file, options);
  try {
    const data = fs.readFileSync(file);
    return Readable.from([Buffer.isBuffer(data) ? data : Buffer.from(data)]);
  } catch (error) {
    return origCreateReadStream(file, options);
  }
};
