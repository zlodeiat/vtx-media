const { __ } = wp.i18n;
export const config = window.vtxMediaConfig;
export async function api(path, options = {}) {
  const url = new URL(config.api + path.split("?")[0]);
  if (path.includes("?"))
    new URLSearchParams(path.split("?")[1]).forEach((v, k) =>
      url.searchParams.set(k, v),
    );
  const response = await fetch(url, {
    credentials: "same-origin",
    ...options,
    headers: {
      "Content-Type": "application/json",
      "X-WP-Nonce": config.nonce,
      ...options.headers,
    },
    body: options.body ? JSON.stringify(options.body) : undefined,
  });
  const data = await response.json().catch(() => ({
    message: __("The server returned an unreadable response.", "vtx-media"),
  }));
  if (!response.ok) {
    const error = new Error(
      data.message || __("Request failed. Please try again.", "vtx-media"),
    );
    error.status = response.status;
    error.code = data.code;
    throw error;
  }
  return data;
}
export function query(path, params) {
  return path + "?" + new URLSearchParams(params);
}
