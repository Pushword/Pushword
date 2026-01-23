export enum LogLevel {
  DEBUG = 0,
  INFO = 1,
  WARN = 2,
  ERROR = 3,
}

class Logger {
  private level: LogLevel = LogLevel.DEBUG
  private isProduction: boolean

  constructor() {
    // Vérifier si on est dans un navigateur
    const isBrowser = typeof window !== 'undefined'

    if (isBrowser) {
      // Dans le navigateur, on est toujours en développement pour le debug
      this.isProduction = false
      this.level = LogLevel.DEBUG
    } else {
      // En Node.js, utiliser process.env
      this.isProduction = process.env.NODE_ENV === 'production'
      if (this.isProduction) {
        this.level = LogLevel.ERROR
      } else {
        this.level = LogLevel.DEBUG
      }
    }
  }

  setLevel(level: LogLevel): void {
    this.level = level
  }

  debug(message: string, ...args: any[]): void {
    if (this.level <= LogLevel.DEBUG) {
      console.debug(`[DEBUG] ${message}`, ...args)
    }
  }

  info(message: string, ...args: any[]): void {
    if (this.level <= LogLevel.INFO) {
      console.info(`[INFO] ${message}`, ...args)
    }
  }

  warn(message: string, ...args: any[]): void {
    if (this.level <= LogLevel.WARN) {
      console.warn(`[WARN] ${message}`, ...args)
    }
  }

  error(message: string, ...args: any[]): void {
    if (this.level <= LogLevel.ERROR) {
      console.error(`[ERROR] ${message}`, ...args)
    }
  }

  // Méthode pour logger les erreurs avec contexte
  logError(error: Error, context: string, additionalInfo?: any): void {
    this.error(`Error in ${context}: ${error.message}`, {
      stack: error.stack,
      ...additionalInfo,
    })
  }
}

export const logger = new Logger()
