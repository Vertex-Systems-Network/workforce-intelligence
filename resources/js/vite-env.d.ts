/// <reference types="vite/client" />


declare global {
  /** Describes the window data contract used by the WorkIntel client. */ interface Window {
    __WORKINTEL_BOOT_LOADER__?: boolean
    __WORKINTEL_REACT_MOUNTED__?: boolean
  }
}

export {}
