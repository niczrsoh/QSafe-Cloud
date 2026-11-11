<?php
/** @var array $_ */
?>
<div id="pqc-registration-container" style="display:flex; justify-content:center; align-items:center; min-height:100vh; background: #f3f4f6; font-family: 'Segoe UI', sans-serif;">
    <div style="width:100%; max-width:450px; background:white; border-radius:20px; padding:40px; box-shadow:0 10px 30px rgba(0,0,0,0.1);">
        <div style="text-align:center; margin-bottom:30px;">
            <div style="font-size:40px; margin-bottom:10px;">🛡️</div>
            <h2 style="color:#1e293b; margin:0; font-size:24px; font-weight:700;">Secure Device Registration</h2>
            <p style="color:#64748b; margin-top:5px; font-size:14px;">Create an account and bind your Key device</p>
        </div>

        <form id="pqc-register-form">
            <div style="margin-bottom:20px;">
                <label style="display:block; margin-bottom:8px; color:#475569; font-weight:600; font-size:14px;">Username</label>
                <input type="text" name="user" required placeholder="Choose a username" autocomplete="username" style="width:100%; padding:12px; border:1.5px solid #e2e8f0; border-radius:10px; font-size:15px; outline:none; box-sizing:border-box;">
            </div>

            <div style="margin-bottom:20px;">
                <label style="display:block; margin-bottom:8px; color:#475569; font-weight:600; font-size:14px;">Email</label>
                <input type="email" name="email" required placeholder="you@example.com" autocomplete="email" style="width:100%; padding:12px; border:1.5px solid #e2e8f0; border-radius:10px; font-size:15px; outline:none; box-sizing:border-box;">
            </div>

            <div style="margin-bottom:20px;">
                <label style="display:block; margin-bottom:8px; color:#475569; font-weight:600; font-size:14px;">Display Name</label>
                <input type="text" name="displayName" placeholder="Your full name" style="width:100%; padding:12px; border:1.5px solid #e2e8f0; border-radius:10px; font-size:15px; outline:none; box-sizing:border-box;">
            </div>

            <div style="margin-bottom:20px;">
                <label style="display:block; margin-bottom:8px; color:#475569; font-weight:600; font-size:14px;">Password</label>
                <div style="position:relative;">
                    <input type="password" id="pqc-password" name="password" required style="width:100%; padding:12px 44px 12px 12px; border:1.5px solid #e2e8f0; border-radius:10px; font-size:15px; outline:none; box-sizing:border-box;">
                    <span id="toggle-password" style="position:absolute; right:12px; top:50%; transform:translateY(-50%); cursor:pointer; color:#64748b; display:flex; align-items:center; justify-content:center; width:28px; height:28px;"></span>
                </div>
            </div>

            <div style="margin-bottom:20px;">
                <label style="display:block; margin-bottom:8px; color:#475569; font-weight:600; font-size:14px;">Confirm Password</label>
                <div style="position:relative;">
                    <input type="password" id="pqc-confirm-password" name="confirm_password" required style="width:100%; padding:12px 44px 12px 12px; border:1.5px solid #e2e8f0; border-radius:10px; font-size:15px; outline:none; box-sizing:border-box;">
                    <span id="toggle-confirm-password" style="position:absolute; right:12px; top:50%; transform:translateY(-50%); cursor:pointer; color:#64748b; display:flex; align-items:center; justify-content:center; width:28px; height:28px;"></span>
                </div>
            </div>


            <div style="margin-bottom:25px; padding:20px; background:#f8fafc; border-radius:12px; border:1px dashed #38bdf8; text-align:center;">
                <label style="display:block; margin-bottom:12px; color:#0369a1; font-weight:700; font-size:13px; text-transform:uppercase; letter-spacing:0.5px;">🔑 Hardware Device Binding</label>
                <p style="margin:0 0 14px; font-size:11px; color:#64748b; line-height:1.5;">Bind at least the primary Key. A secondary Key is optional but recommended.</p>

                <div style="padding:14px; background:white; border-radius:10px; border:1px solid #bae6fd; margin-bottom:12px;">
                    <div style="font-size:12px; color:#0369a1; font-weight:700; margin-bottom:8px;">Primary Key</div>
                    <button type="button" id="bind-primary-pqc-btn" style="width:100%; padding:10px; background:#38bdf8; color:white; border:none; border-radius:8px; font-size:13px; font-weight:600; cursor:pointer;">
                        🔌 Bind Primary Key
                    </button>
                    <div id="primary-pqc-key-display" style="display:none; margin-top:12px;">
                        <div style="font-size:11px; color:#64748b; margin-bottom:4px;">Primary Device ID:</div>
                        <div id="primary-pqc-key-value" style="font-family:monospace; font-size:11px; word-break:break-all; color:#0369a1; background:#f8fafc; padding:10px; border-radius:8px; border:1px solid #bae6fd; font-weight:bold; text-align:left;"></div>
                    </div>
                    <input type="hidden" name="pqcKey" id="pqc-key-input">
                    <p id="primary-pqc-status-msg" style="margin-top:10px; font-size:11px; color:#64748b;">Connect your primary Key via USB, then click Bind.</p>
                </div>

                <div style="padding:14px; background:white; border-radius:10px; border:1px solid #e2e8f0;">
                    <div style="font-size:12px; color:#475569; font-weight:700; margin-bottom:8px;">secondary Key</div>
                    <button type="button" id="bind-backup-pqc-btn" style="width:100%; padding:10px; background:#64748b; color:white; border:none; border-radius:8px; font-size:13px; font-weight:600; cursor:pointer;">
                        🔌 Bind secondary Key
                    </button>
                    <div id="backup-pqc-key-display" style="display:none; margin-top:12px;">
                        <div style="font-size:11px; color:#64748b; margin-bottom:4px;">secondary Device ID:</div>
                        <div id="backup-pqc-key-value" style="font-family:monospace; font-size:11px; word-break:break-all; color:#475569; background:#f8fafc; padding:10px; border-radius:8px; border:1px solid #e2e8f0; font-weight:bold; text-align:left;"></div>
                    </div>
                    <input type="hidden" name="backupPqcKey" id="backup-pqc-key-input">
                    <p id="backup-pqc-status-msg" style="margin-top:10px; font-size:11px; color:#64748b;">Optional: connect a second Key and bind it as secondary.</p>
                </div>
            </div>

            <button type="submit" id="submit-btn" disabled style="width:100%; padding:14px; background:#94a3b8; color:white; border:none; border-radius:12px; font-size:16px; font-weight:600; cursor:not-allowed;">
                Register & Bind
            </button>

            <div style="text-align:center; margin-top:20px;">
                <a href="/index.php/login" style="color:#64748b; font-size:14px; text-decoration:none;">Back to Login</a>
            </div>
        </form>

        <!-- SSS Recovery Page (Hidden by default) -->
        <div id="sss-display-container" style="display:none;">
            <div style="text-align:center; margin-bottom:25px;">
                <div style="font-size:40px; margin-bottom:10px;">🛡️</div>
                <h2 style="color:#1e293b; margin:0; font-size:22px; font-weight:700;">Security Setup: Recovery Keys</h2>
                <p style="color:#64748b; margin-top:5px; font-size:13px;">If you lose your Key, you will need these to recover your account.</p>
            </div>

            <div style="margin-bottom:25px; padding:20px; background:#f0f9ff; border-radius:12px; border:1px solid #bae6fd;">
                <label style="display:block; margin-bottom:8px; color:#0369a1; font-weight:700; font-size:12px; text-transform:uppercase;">Share B: Recovery String</label>
                <div style="display:flex; gap:8px;">
                    <div id="sss-share-b" style="flex:1; font-family:monospace; font-size:14px; background:white; padding:12px; border-radius:8px; border:1px solid #bae6fd; color:#0369a1; font-weight:bold; letter-spacing:1px;"></div>
                    <button id="copy-share-b" style="padding:0 15px; background:#38bdf8; color:white; border:none; border-radius:8px; font-size:12px; font-weight:600; cursor:pointer;">Copy</button>
                </div>
                <p style="margin-top:8px; font-size:11px; color:#64748b;">Write this down or take a photo. Do not share it.</p>
            </div>

            <div style="margin-bottom:25px; padding:20px; background:#f8fafc; border-radius:12px; border:1px solid #e2e8f0;">
                <label style="display:block; margin-bottom:8px; color:#475569; font-weight:700; font-size:12px; text-transform:uppercase;">Share C: Recovery File</label>
                <button id="download-share-c" style="width:100%; padding:12px; background:#0f172a; color:white; border:none; border-radius:10px; font-size:14px; font-weight:600; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:8px;">
                    📥 Download recovery file
                </button>
                <p style="margin-top:8px; font-size:11px; color:#64748b;">Store this file in a safe place (USB drive or cloud vault).</p>
            </div>

            <div style="margin-bottom:25px; padding:20px; background:#f8fafc; border-radius:12px; border:1px solid #e2e8f0;">
                <label style="display:block; margin-bottom:8px; color:#475569; font-weight:700; font-size:12px; text-transform:uppercase;">Knowledge-Based Recovery</label>
                <p style="margin:0 0 12px; font-size:11px; color:#64748b; line-height:1.5;">Avoid answers that appear on social media. Set 3 answers. During recovery, any 2 correct answers are required with your recovery share.</p>
                <div style="margin-bottom:12px;">
                    <select id="sss-kba-question-1" required title="Choose recovery security question 1" style="width:100%; max-width:100%; min-height:48px; padding:12px 40px 12px 12px; border:1.5px solid #e2e8f0; border-radius:10px; font-size:14px; line-height:1.4; outline:none; box-sizing:border-box; margin-bottom:8px; background:white; color:#334155;">
                    <option value="">Choose a security question</option>
                    <option value="What private phrase will you remember for recovery?">Private recovery phrase</option>
                    <option value="What is a private nickname only you use for yourself?">Private personal nickname</option>
                    <option value="What was the first place you travelled to alone?">First solo travel place</option>
                    <option value="What was the name of a childhood place you secretly liked?">Private childhood place</option>
                    <option value="What object did you keep for sentimental reasons?">Sentimental object</option>
                    <option value="What is a memorable sentence from your childhood?">Childhood sentence</option>
                    <option value="What was your favourite movie during childhood?">Childhood favourite movie</option>
                    <option value="What was the name of your first school?">First school name</option>
                    <option value="What was your childhood nickname?">Childhood nickname</option>
                    <option value="What city were you born in?">Birth city</option>
                    </select>
                    <input type="text" id="sss-kba-answer-1" required placeholder="Answer 1" autocomplete="off" style="width:100%; padding:12px; border:1.5px solid #e2e8f0; border-radius:10px; font-size:15px; outline:none; box-sizing:border-box;">
                </div>
                <div style="margin-bottom:12px;">
                    <select id="sss-kba-question-2" required title="Choose recovery security question 2" style="width:100%; max-width:100%; min-height:48px; padding:12px 40px 12px 12px; border:1.5px solid #e2e8f0; border-radius:10px; font-size:14px; line-height:1.4; outline:none; box-sizing:border-box; margin-bottom:8px; background:white; color:#334155;">
                    <option value="">Choose a security question</option>
                    <option value="What private phrase will you remember for recovery?">Private recovery phrase</option>
                    <option value="What is a private nickname only you use for yourself?">Private personal nickname</option>
                    <option value="What was the first place you travelled to alone?">First solo travel place</option>
                    <option value="What was the name of a childhood place you secretly liked?">Private childhood place</option>
                    <option value="What object did you keep for sentimental reasons?">Sentimental object</option>
                    <option value="What is a memorable sentence from your childhood?">Childhood sentence</option>
                    <option value="What was your favourite movie during childhood?">Childhood favourite movie</option>
                    <option value="What was the name of your first school?">First school name</option>
                    <option value="What was your childhood nickname?">Childhood nickname</option>
                    <option value="What city were you born in?">Birth city</option>
                    </select>
                    <input type="text" id="sss-kba-answer-2" required placeholder="Answer 2" autocomplete="off" style="width:100%; padding:12px; border:1.5px solid #e2e8f0; border-radius:10px; font-size:15px; outline:none; box-sizing:border-box;">
                </div>
                <div style="margin-bottom:0px;">
                    <select id="sss-kba-question-3" required title="Choose recovery security question 3" style="width:100%; max-width:100%; min-height:48px; padding:12px 40px 12px 12px; border:1.5px solid #e2e8f0; border-radius:10px; font-size:14px; line-height:1.4; outline:none; box-sizing:border-box; margin-bottom:8px; background:white; color:#334155;">
                    <option value="">Choose a security question</option>
                    <option value="What private phrase will you remember for recovery?">Private recovery phrase</option>
                    <option value="What is a private nickname only you use for yourself?">Private personal nickname</option>
                    <option value="What was the first place you travelled to alone?">First solo travel place</option>
                    <option value="What was the name of a childhood place you secretly liked?">Private childhood place</option>
                    <option value="What object did you keep for sentimental reasons?">Sentimental object</option>
                    <option value="What is a memorable sentence from your childhood?">Childhood sentence</option>
                    <option value="What was your favourite movie during childhood?">Childhood favourite movie</option>
                    <option value="What was the name of your first school?">First school name</option>
                    <option value="What was your childhood nickname?">Childhood nickname</option>
                    <option value="What city were you born in?">Birth city</option>
                    </select>
                    <input type="text" id="sss-kba-answer-3" required placeholder="Answer 3" autocomplete="off" style="width:100%; padding:12px; border:1.5px solid #e2e8f0; border-radius:10px; font-size:15px; outline:none; box-sizing:border-box;">
                </div>
            </div>

            <button id="finish-sss-btn" style="width:100%; padding:14px; background:#22c55e; color:white; border:none; border-radius:12px; font-size:16px; font-weight:600; cursor:pointer;">
                I have saved my recovery keys
            </button>
        </div>
    </div>
</div>
