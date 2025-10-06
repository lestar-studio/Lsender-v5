const {
    default: WASocket,
    DisconnectReason,
    useMultiFileAuthState,
    fetchLatestWaWebVersion,
    makeCacheableSignalKeyStore,
} = require("@whiskeysockets/baileys");
const { logger } = require("../app/lib/myf.velixs.js");
const pino = require("pino");
const qrcode = require("qrcode");
const fs = require("fs");
const SessionsDatabase = require("../app/database/sessions.db.js");
const { Boom } = require("@hapi/boom");
const Message = require("./Client/MessageHandler.js");
const Bulk = require("./Client/Bulk.js");
const eventEmitter = require("./../app/lib/Event.js");
const NodeCache = require("node-cache");
let sessionMap = new Map();

class SessionConnection extends SessionsDatabase {

    constructor(socket) {
        super();
        this.socket = socket;
        this.storagePath = __dirname + "/../storage";
        this.sessionPath = this.storagePath + "/sessions";
        this.time_out_qr = 0;
    }

    async getSession(session) {
        return sessionMap.get(session) ? sessionMap.get(session) : null
    }

    async deleteSession(session) {
        try {
            sessionMap.delete(session);
            if (fs.existsSync(`${this.sessionPath}/${session}`)) fs.rmSync(`${this.sessionPath}/${session}`, { force: true, recursive: true });
            logger("info", "[SESSION] SESSION DELETED : " + `${session} `);
        } catch (e) {
            logger("error", "[SESSION] SESSION DELETED ERROR : " + `${session} `);
        }
    }

    async generateQr(input, session) {
        let rawData = await qrcode.toDataURL(input, { scale: 8 });
        // wait 3 seconds
        setTimeout(() => {
            this.socket.emit(`servervelixs`, {
                status: true,
                code_message: "qr200",
                session_id: session,
                qr: rawData,
            });
        }, 2000);
        this.time_out_qr++;
        logger("info", "[SESSION] WAITING FOR THE SCAN QR : " + `${session} ` + `(${this.time_out_qr})`);
        this.socket.emit('logger', {
            session_id: session,
            type: 'info',
            message: `[SESSION] WAITING FOR THE SCAN QR ( ${this.time_out_qr} OF ${process.env.TIME_OUT_QR} ).`
        })
    }

    async autoStart() {
        let session = await this.table.findAll({ where: { status: 'CONNECTED' } });
        if (session.length > 0) {
            session.forEach(async (session) => {
                if (fs.existsSync(`${this.sessionPath}/${session.id}`)) {
                    logger("info", "[SESSION] AUTO START : " + `${session.session_name} `);
                    await this.createSession(session.id);
                } else {
                    logger("info", "[SESSION] AUTO START ERROR : " + `${session.session_name} `);
                    await this.updateStatus(session.id);
                }
            });
        }
    }

    async createSession(session) {
        var unknown_attempt = 0;
        let retryCount = 0;
        const msgRetryCounterCache = new NodeCache()
        const sessionDir = `${this.sessionPath}/${session}`;
        const storePath = `${this.sessionPath}/${session}/store_walix.json`;
        if (!fs.existsSync(sessionDir)) fs.mkdirSync(sessionDir, { recursive: true });
        let { state, saveCreds } = await useMultiFileAuthState(sessionDir);
        const { version, isLatest } = await fetchLatestWaWebVersion();
        const velixs = WASocket({
            printQRInTerminal: false,
            auth: {
                creds: state.creds,
                keys: makeCacheableSignalKeyStore(state.keys, pino().child({
                    level: 'silent',
                    stream: 'store'
                })),
            },
            logger: pino({ level: "silent" }),
            browser: ["Wibble", "Chrome", "3.0.0"],
            markOnlineOnConnect: true,
            version,
            msgRetryCounterCache,
            defaultQueryTimeoutMs: undefined,
        });

        sessionMap.set(session, { ...velixs, isStop: false }); // add session to map

        // event
        velixs.ev.on("creds.update", saveCreds);

        velixs.ev.on("connection.update", async (update) => {
            if (update.isNewLogin) {
                try {
                    if (await this.findSessionId(session)) {
                        await this.updateStatus(session, 'CONNECTED', velixs.authState.creds.me.id.split(":")[0]);
                    } else {
                        this.socket.emit('logger', {
                            session_id: session,
                            type: 'error',
                            message: `[DEVICE] DEVICE NOT FOUND, PLEASE REFRESH PAGE.`
                        })
                        this.socket.emit(`servervelixs`, {
                            status: true,
                            code_message: "device404",
                            session_id: session,
                            message: "DEVICE NOT FOUND.",
                        });
                        velixs.ev.removeAllListeners("connection.update");
                        velixs.end();
                        return this.deleteSession(session);
                    }
                    this.socket.emit(`logger`, {
                        session_id: session,
                        type: 'debug',
                        message: `[SESSION] NEW CONNECTED.`
                    })
                    this.socket.emit(`servervelixs`, {
                        status: true,
                        code_message: "sessionconnected",
                        session_id: session,
                        session: {
                            name: velixs.authState.creds.me.name,
                            number: velixs.authState.creds.me.id.split(":")[0],
                            platform: velixs.authState.creds.platform,
                            log: '',
                        }
                    });
                    return eventEmitter.emit('wa.connection', {
                        session_id: session,
                        status: 'open',
                    });
                } catch (e) { }
            } else {
                if (update.qr) {
                    try {
                        if (this.time_out_qr >= process.env.TIME_OUT_QR) {
                            velixs.ev.removeAllListeners("connection.update");
                            this.deleteSession(session);
                            logger("debug", "[SESSION] SESSION END : " + `${session}`);
                            this.socket.emit('logger', {
                                session_id: session,
                                type: 'debug',
                                message: `[SESSION] SESSION END, PLEASE REGENERATE QR CODE.`
                            })
                            this.socket.emit(`servervelixs`, {
                                status: true,
                                code_message: "regenerateqr",
                                session_id: session,
                                message: "QR Code Expired",
                            });
                            return;
                        }
                        this.generateQr(update.qr, session);
                    } catch (e) { }
                }
            }

            try {
                const { lastDisconnect, connection } = update;
                if (connection === "close") {
                    if (connection === "close") {
                        const code = lastDisconnect?.error?.output?.statusCode || lastDisconnect?.error?.output?.payload?.statusCode
                        velixs.connected = false
                        let retryAttempt = retryCount;
                        let shouldRetry;
                        if (code != DisconnectReason.loggedOut && retryAttempt < 20) {
                            shouldRetry = true;
                        }
                        if (shouldRetry) {
                            retryAttempt++;
                        }
                        if (shouldRetry) {
                            retryCount = retryAttempt
                            this.socket.emit(`servervelixs`, {
                                code_message: "reconnect",
                                session_id: session,
                                message: "Connection Lost, Reconnecting...",
                            });
                            this.socket.emit('logger', {
                                session_id: session,
                                type: 'debug',
                                message: `[SESSION] CONNECTION LOST, RECONNECTING...`
                            });
                            logger("debug", "[SESSION] CONNECTION LOST, RECONNECTING..." + `${lastDisconnect?.error?.toString()}`);
                            velixs.ev.removeAllListeners("connection.update");
                            velixs.end();
                            this.createSession(session);
                        } else {
                            this.socket.emit('logger', {
                                session_id: session,
                                type: 'debug',
                                message: `[SESSION] DISCONNECTED, PLEASE REGENERATE QR CODE.`
                            });
                            retryCount = 0
                            velixs?.logout()
                            velixs.ev.removeAllListeners("connection.update");
                            velixs.end();
                            this.deleteSession(session);
                        }
                    }
                    this.updateStatus(session);
                    eventEmitter.emit('wa.connection', {
                        session_id: session,
                        status: 'close',
                    });
                } else if (connection == "open") {
                    await this.updateStatus(session, 'CONNECTED', velixs.authState.creds.me.id.split(":")[0]);
                    logger("debug", "[SESSION] CONNECTED : " + `${session}`);
                    this.socket.emit(`logger`, {
                        session_id: session,
                        type: 'debug',
                        message: `[SESSION] NEW CONNECTED.`
                    })
                    this.socket.emit(`servervelixs`, {
                        status: true,
                        code_message: "sessionconnected",
                        session_id: session,
                        session: {
                            name: velixs.authState.creds.me.name,
                            number: velixs.authState.creds.me.id.split(":")[0],
                            platform: velixs.authState.creds.platform,
                            log: '',
                        }
                    });
                    eventEmitter.emit('wa.connection', {
                        session_id: session,
                        status: 'open',
                    });
                }
            } catch (e) {
                console.log(e);
            }
        });

        velixs.ev.on("messages.upsert", async (chatUpdate) => {
            if (chatUpdate.type !== "notify") return;
            const message = new Message(velixs, chatUpdate.messages[0], session);
            message.mainHandler();
        });

        new Bulk(velixs, session).mainHandler();
    }

}

module.exports = SessionConnection;
