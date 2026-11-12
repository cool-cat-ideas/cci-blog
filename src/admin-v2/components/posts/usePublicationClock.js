import { useSyncExternalStore } from 'react';
import { pluginData } from '../../api/api';

const offset = pluginData.serverTime ? Number(pluginData.serverTime) * 1000 - Date.now() : 0;
const listeners = new Set();
let now = Date.now();
let timer;
const snapshot = () => now + offset;
const subscribe = (listener) => {
  listeners.add(listener);
  if (!timer) {
    now = Date.now();
    timer = window.setInterval(() => {
      now = Date.now();
      [...listeners].forEach((notify) => notify());
    }, 30000);
  }
  return () => {
    listeners.delete(listener);
    if (!listeners.size) {
      window.clearInterval(timer);
      timer = undefined;
    }
  };
};

/** All visible article badges share one publication clock. */
export function usePublicationClock() {
  return useSyncExternalStore(subscribe, snapshot, snapshot);
}
