class Logger {
  constructor() {
    this.level = 2;
    this.isProduction = true;
    if (this.isProduction) {
      this.level = 3;
    }
  }
  setLevel(level) {
    this.level = level;
  }
  debug(message, ...args) {
    if (this.level <= 0 && !this.isProduction) {
      console.debug(`[DEBUG] ${message}`, ...args);
    }
  }
  info(message, ...args) {
    if (this.level <= 1 && !this.isProduction) {
      console.info(`[INFO] ${message}`, ...args);
    }
  }
  warn(message, ...args) {
    if (this.level <= 2) {
      console.warn(`[WARN] ${message}`, ...args);
    }
  }
  error(message, ...args) {
    if (this.level <= 3) {
      console.error(`[ERROR] ${message}`, ...args);
    }
  }
  // Méthode pour logger les erreurs avec contexte
  logError(error, context, additionalInfo) {
    this.error(`Error in ${context}: ${error.message}`, {
      stack: error.stack,
      ...additionalInfo
    });
  }
}
const logger = new Logger();
export {
  logger as l
};
